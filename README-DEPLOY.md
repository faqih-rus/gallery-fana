# Deploy — Galeri Romantis (PHP + Docker)

Aplikasi PHP (bukan statis), jadi disajikan oleh **Apache + mod_php** di dalam satu
kontainer (`php:8.4-apache`), di belakang **Host Nginx Reverse Proxy + SSL**.

## File deploy
- `Dockerfile` — image PHP 8.4 + Apache.
- `apache-app.conf` — `gallery.php` jadi halaman utama, kunci folder `data/`, matikan PHP di `uploads/`.
- `docker-compose.yml` — build + port + **volume data persisten**.
- `deploy.sh` — pull → build → up → reverse proxy + SSL.
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
Foto (`uploads/`) dan database (`data/photos.json`) disimpan di **host**, bukan di
dalam image:
```
/var/lib/galeri-romantis/uploads
/var/lib/galeri-romantis/data
```
`deploy.sh` membuat folder ini dan men-set kepemilikan ke `www-data` (uid 33)
sebelum kontainer naik. Rebuild / `git pull` **tidak** menyentuhnya.
> Kalau mengganti `APP_NAME`, samakan juga path volume di `docker-compose.yml`.

## Update tiap ada perubahan di branch
Cukup jalankan ulang di server:
```bash
./deploy.sh
```
Ia akan `git pull` branch `main`, rebuild image, dan restart kontainer — data tetap aman.

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
tar czf galeri-backup-$(date +%F).tgz /var/lib/galeri-romantis
```

## Catatan
- Batas upload diset 16 MB (Nginx `client_max_body_size`). PHP default `upload_max_filesize`
  biasanya 2 MB — kalau perlu lebih besar, tambah file `uploads.ini` (`upload_max_filesize=16M`,
  `post_max_size=16M`) dan `COPY` ke `/usr/local/etc/php/conf.d/` di Dockerfile.
- Ganti `ADMIN_PASSWORD` & `GALLERY_TITLE` di `config.php` sebelum go-live.
- Alternatif arsitektur: Nginx + PHP-FPM (dua proses). Lebih "rapi" tapi lebih rumit;
  untuk skala ini `php:apache` sudah cukup.
