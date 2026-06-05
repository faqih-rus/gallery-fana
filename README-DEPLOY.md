# Deploy — Galeri Romantis (PHP + Docker)

Aplikasi PHP (bukan statis), jadi disajikan oleh **Apache + mod_php** di dalam satu
kontainer (`php:8.4-apache`), di belakang **Host Nginx Reverse Proxy + SSL**.

## File deploy
- `Dockerfile` — image PHP 8.4 + Apache (sudah termasuk GD + EXIF untuk thumbnail).
- `apache-app.conf` — `gallery.php` jadi halaman utama, kunci folder `data/`, matikan PHP di `uploads/`.
- `docker-compose.yml` — **produksi** (port 8094) — build + port + volume data persisten.
- `deploy.sh` — produksi: pull branch `cinta` → build → up → reverse proxy + SSL.
- `docker-compose.dvlp.yml` — **development** (port 8095, container `-dvlp`, data dibagi dengan prod).
- `deploy-dvlp.sh` — development: pull branch `dvlp` → build → up → proxy `fana-dvlp.luvforever.net`.
- `.dockerignore`, `.gitignore` — jaga data & file deploy tidak ikut.

## Sekali jalan
1. Edit blok **Konfigurasi** di `deploy.sh` (`REPO_URL`, `DOMAIN`, `HOST_PORT`, `SSL_EMAIL`).
2. Samakan `HOST_PORT` dengan `ports:` di `docker-compose.yml`.
3. Arahkan DNS domain ke IP server (untuk SSL).
4. Jalankan:
   ```bash
   chmod +x deploy.sh
   ./deploy.sh
   ```
   Galeri: `https://<domain>/` · Admin: `https://<domain>/admin.php`

## Data tidak hilang saat update (PENTING)
Foto (`uploads/`), database (`data/photos.json`), dan komentar (`data/comments.json`)
disimpan di **host**, bukan di dalam image:
```
/var/lib/gallery-fana/uploads
/var/lib/gallery-fana/data
```
`deploy.sh` membuat folder ini dan men-set kepemilikan ke `www-data` (uid 33)
sebelum kontainer naik. Rebuild / `git pull` **tidak** menyentuhnya.
> Path ini harus sama di `deploy.sh` (`DATA_ROOT`) dan `docker-compose.yml` (volume).
> **Development memakai folder yang sama** sehingga data dev & prod sinkron.

## Update tiap ada perubahan di branch
Cukup jalankan ulang di server:
```bash
./deploy.sh
```
Ia akan `git pull` branch `cinta`, rebuild image, dan restart kontainer — data tetap aman.

## Development berdampingan (branch `dvlp`)
Lingkungan dev jalan **bersamaan** dengan prod: container & image terpisah (`-dvlp`),
port **8095**, domain `fana-dvlp.luvforever.net`, tapi **berbagi folder data yang sama**
(`/var/lib/gallery-fana`) sehingga upload/komentar langsung sinkron dengan prod.
```bash
# di server (checkout terpisah otomatis di /var/www/gallery-fana-dvlp)
chmod +x deploy-dvlp.sh
./deploy-dvlp.sh                 # pull branch dvlp + build + up (port 8095)
./deploy-dvlp.sh logs|status|down
```
Cek keduanya hidup: `docker ps` → `galeri-romantis` (prod) **dan** `galeri-romantis-dvlp` (dev).

**Alur git:** kerja di `dvlp` → uji di `fana-dvlp` → kalau oke, merge `dvlp` → `cinta`
(prod). Sesekali rebase/merge `cinta` → `dvlp` agar dev tetap selaras.

> ⚠️ Data dibagi: aksi di dev (hapus foto/komentar) **langsung** mengubah data prod.
> Kalau ingin aman, pisahkan `DATA_ROOT` dev ke folder lain lalu sinkron manual.

Mau otomatis tanpa SSH manual? Pilih salah satu:
- **Cron** (poll tiap 5 menit, deploy kalau ada commit baru):
  ```cron
  */5 * * * * cd /var/www/galeri-romantis && git fetch -q && \
    [ "$(git rev-parse HEAD)" != "$(git rev-parse @{u})" ] && /var/www/galeri-romantis/deploy.sh >> /var/log/galeri-deploy.log 2>&1
  ```
- **Webhook GitHub** → endpoint kecil di server yang menjalankan `deploy.sh` saat ada `push`.

## Backup
Cukup arsipkan folder host:
```bash
tar czf galeri-backup-$(date +%F).tgz /var/lib/gallery-fana
```

## Catatan
- Batas upload diset 16 MB (Nginx `client_max_body_size`). PHP default `upload_max_filesize`
  biasanya 2 MB — kalau perlu lebih besar, tambah file `uploads.ini` (`upload_max_filesize=16M`,
  `post_max_size=16M`) dan `COPY` ke `/usr/local/etc/php/conf.d/` di Dockerfile.
- Ganti `ADMIN_PASSWORD` & `GALLERY_TITLE` di `config.php` sebelum go-live.
- Alternatif arsitektur: Nginx + PHP-FPM (dua proses). Lebih "rapi" tapi lebih rumit;
  untuk skala ini `php:apache` sudah cukup.
