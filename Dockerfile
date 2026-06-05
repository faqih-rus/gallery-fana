# Galeri romantis — PHP 8.4 + Apache (mod_php, satu kontainer)
FROM php:8.4-apache

# Modul Apache yang dipakai
RUN a2enmod rewrite headers

# Ekstensi PHP: GD (resize thumbnail) + EXIF (perbaiki rotasi foto HP)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd exif \
    && rm -rf /var/lib/apt/lists/*

# Konfigurasi app: gallery.php sebagai index, proteksi folder data & uploads
COPY apache-app.conf /etc/apache2/conf-enabled/zz-app.conf

# Salin seluruh kode aplikasi ke document root
COPY . /var/www/html/

# Buang file deploy & data lokal supaya tidak ikut tersaji
RUN rm -rf \
    /var/www/html/.git \
    /var/www/html/Dockerfile \
    /var/www/html/docker-compose.yml \
    /var/www/html/deploy.sh \
    /var/www/html/apache-app.conf \
    /var/www/html/README-DEPLOY.md \
    /var/www/html/.dockerignore \
    /var/www/html/.gitignore \
    2>/dev/null || true

# Pastikan folder data & uploads ada dan dimiliki Apache (www-data = uid 33).
# Saat dijalankan, keduanya akan ditimpa volume host (lihat docker-compose.yml).
RUN mkdir -p /var/www/html/uploads /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data

EXPOSE 80

# Healthcheck tanpa dependensi tambahan (pakai PHP yang sudah ada di image)
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/")?0:1);'
