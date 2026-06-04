#!/usr/bin/env bash
# =============================================================================
#  deploy.sh — galeri-romantis
#  Deploy aplikasi PHP (Apache + mod_php) lewat Docker, di belakang
#  Host Nginx Reverse Proxy + SSL (Let's Encrypt / Certbot).
#
#  Cara pakai:
#     chmod +x deploy.sh
#     ./deploy.sh            # pull terbaru + build + jalankan
#     ./deploy.sh --no-pull  # skip git pull (build dari kode lokal)
#     ./deploy.sh down       # matikan container
#     ./deploy.sh logs       # lihat log
#     ./deploy.sh status     # cek status
# =============================================================================

set -Eeuo pipefail

# --------------------------- Konfigurasi (EDIT INI) --------------------------
APP_NAME="galeri-romantis"
APP_DIR="/var/www/galeri-romantis"
REPO_URL="https://github.com/USER/galeri-romantis.git"   # ganti
HOST_PORT="8090"                       # samakan dengan ports di docker-compose.yml
PUBLIC_IP="0.0.0.0"                    # ganti dengan IP server (info saja)
DOMAIN="galeri.example.com"           # ganti dengan domainmu
GIT_BRANCH="main"
SSL_EMAIL="admin@example.com"         # email notifikasi Let's Encrypt

# Lokasi data persisten di HOST (HARUS sama dengan path volume di docker-compose.yml)
DATA_ROOT="/var/lib/${APP_NAME}"
WWW_DATA_UID="33"                      # uid www-data di image php:apache (Debian)

# --------------------------- Util warna --------------------------------------
C_RST="\033[0m"; C_GRN="\033[1;32m"; C_YEL="\033[1;33m"; C_RED="\033[1;31m"; C_BLU="\033[1;34m"
log()  { echo -e "${C_BLU}[ deploy ]${C_RST} $*"; }
ok()   { echo -e "${C_GRN}[   ok   ]${C_RST} $*"; }
warn() { echo -e "${C_YEL}[  warn  ]${C_RST} $*"; }
err()  { echo -e "${C_RED}[  err   ]${C_RST} $*" >&2; }
trap 'err "Gagal pada baris $LINENO. Deploy dibatalkan."' ERR

# --------------------------- Deteksi compose ---------------------------------
detect_compose() {
  if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
  elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE="docker-compose"
  else
    err "Docker Compose tidak ditemukan. Install Docker + plugin compose dulu."
    exit 1
  fi
}

# --------------------------- Cek prasyarat -----------------------------------
need_root_or_sudo() {
  if [ "$(id -u)" -ne 0 ]; then
    if command -v sudo >/dev/null 2>&1; then SUDO="sudo"; else err "Butuh root atau sudo."; exit 1; fi
  else
    SUDO=""
  fi
}

check_docker() {
  command -v docker >/dev/null 2>&1 || { err "Docker belum terpasang."; exit 1; }
  $SUDO docker info >/dev/null 2>&1 || { err "Docker daemon tidak aktif."; exit 1; }
}

# --------------------------- Sub-command -------------------------------------
do_down()   { cd "$APP_DIR"; log "Mematikan container..."; $SUDO $COMPOSE down; ok "Container dimatikan."; }
do_logs()   { cd "$APP_DIR"; $SUDO $COMPOSE logs -f --tail=100; }
do_status() {
  cd "$APP_DIR"; $SUDO $COMPOSE ps; echo
  log "Cek HTTP lokal (port ${HOST_PORT})..."
  if curl -fsS -o /dev/null -w "HTTP %{http_code}\n" "http://127.0.0.1:${HOST_PORT}/"; then
    ok "Aplikasi merespons di port ${HOST_PORT}."
  else
    warn "Belum ada respons di port ${HOST_PORT}."
  fi
}

# --------------------------- Update kode -------------------------------------
sync_repo() {
  if [ ! -d "$APP_DIR/.git" ]; then
    warn "Belum ada repo di $APP_DIR — meng-clone..."
    $SUDO mkdir -p "$APP_DIR"
    $SUDO git clone "$REPO_URL" "$APP_DIR"
  elif [ "${DO_PULL:-1}" = "1" ]; then
    log "git pull origin ${GIT_BRANCH}..."
    cd "$APP_DIR"
    $SUDO git fetch --all --prune
    $SUDO git checkout "$GIT_BRANCH" 2>/dev/null || true
    $SUDO git pull --ff-only origin "$GIT_BRANCH" || warn "git pull dilewati."
  else
    log "Lewati git pull (--no-pull)."
  fi
}

# --------------------------- Pastikan file repo ada --------------------------
verify_repo_files() {
  cd "$APP_DIR"
  local missing=0
  for f in Dockerfile docker-compose.yml apache-app.conf gallery.php admin.php config.php; do
    if [ ! -f "$f" ]; then err "File wajib hilang di repo: $f"; missing=1; fi
  done
  [ "$missing" = "0" ] || { err "Lengkapi file di repo lalu jalankan lagi."; exit 1; }
}

# --------------------------- Folder data persisten (HOST) --------------------
prepare_data_dirs() {
  log "Menyiapkan folder data persisten di host..."
  $SUDO mkdir -p "${DATA_ROOT}/uploads" "${DATA_ROOT}/data"
  # Apache di kontainer berjalan sebagai www-data (uid 33) → harus bisa menulis.
  $SUDO chown -R "${WWW_DATA_UID}:${WWW_DATA_UID}" "${DATA_ROOT}/uploads" "${DATA_ROOT}/data"
  ok "Data persisten siap di ${DATA_ROOT} (uploads, data) — aman dari rebuild."
}

# --------------------------- Build & up --------------------------------------
build_up() {
  cd "$APP_DIR"
  log "Build image & menjalankan container..."
  $SUDO $COMPOSE up -d --build
  ok "Container berjalan."
}

# --------------------------- Verifikasi --------------------------------------
verify() {
  log "Menunggu container siap..."
  sleep 4
  local code
  code="$(curl -fsS -o /dev/null -w "%{http_code}" "http://127.0.0.1:${HOST_PORT}/" || echo "000")"
  if [ "$code" = "200" ]; then
    ok "Aplikasi sehat (HTTP 200) di port ${HOST_PORT}."
  else
    warn "Respons HTTP: ${code}. Cek log: ./deploy.sh logs"
  fi
}

# --------------------------- Reverse Proxy & SSL (Host) ----------------------
setup_proxy_and_ssl() {
  log "Menyiapkan Reverse Proxy & SSL di Host Nginx..."
  if ! command -v nginx >/dev/null 2>&1; then
    warn "Nginx tidak terdeteksi di host. Lewati konfigurasi proxy."
    return
  fi

  local CONF_FILE="/etc/nginx/sites-available/${APP_NAME}.conf"
  local SYMLINK="/etc/nginx/sites-enabled/${APP_NAME}.conf"

  # Naikkan batas ukuran upload di proxy (galeri mengunggah gambar)
  $SUDO tee "$CONF_FILE" >/dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    client_max_body_size 16m;

    location / {
        proxy_pass http://127.0.0.1:${HOST_PORT};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

  [ -L "$SYMLINK" ] || $SUDO ln -s "$CONF_FILE" "$SYMLINK"
  $SUDO nginx -t && $SUDO systemctl reload nginx
  ok "Nginx Reverse Proxy siap."

  if command -v certbot >/dev/null 2>&1; then
    log "Request SSL via Certbot untuk ${DOMAIN}..."
    $SUDO certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${SSL_EMAIL}" --redirect \
      || warn "Gagal request SSL. Pastikan DNS sudah mengarah ke server & sudah propagasi."
  else
    warn "Certbot belum ada. Install: sudo apt install certbot python3-certbot-nginx"
  fi
}

# --------------------------- Ringkasan ---------------------------------------
summary() {
  echo
  echo -e "${C_GRN}=============================================================${C_RST}"
  echo -e "${C_GRN}  DEPLOY SELESAI — ${APP_NAME}${C_RST}"
  echo -e "${C_GRN}=============================================================${C_RST}"
  echo -e "  Lokal Container : http://127.0.0.1:${HOST_PORT}"
  echo -e "  Publik          : https://${DOMAIN}"
  echo -e "  Galeri          : https://${DOMAIN}/"
  echo -e "  Admin           : https://${DOMAIN}/admin.php"
  echo -e "  Data persisten  : ${DATA_ROOT}/{uploads,data}"
  echo
  echo -e "  ./deploy.sh logs | status | down"
  echo -e "${C_GRN}=============================================================${C_RST}"
}

# --------------------------- Main --------------------------------------------
main() {
  need_root_or_sudo
  detect_compose
  check_docker

  case "${1:-up}" in
    down)      do_down;   exit 0 ;;
    logs)      do_logs;   exit 0 ;;
    status)    do_status; exit 0 ;;
    up|"")     : ;;
    --no-pull) DO_PULL=0 ;;
    *) err "Argumen tidak dikenal: $1"; exit 1 ;;
  esac
  for arg in "$@"; do [ "$arg" = "--no-pull" ] && DO_PULL=0; done

  log "Mulai deploy ${APP_NAME}..."
  sync_repo
  verify_repo_files
  prepare_data_dirs
  build_up
  verify
  setup_proxy_and_ssl
  summary
}

main "$@"
