# Usa una imagen base adecuada
FROM php:7.4-fpm

# Debian Bullseye está archivado: deb.debian.org ya no publica los paquetes de
# security y apt devuelve 404. Apuntamos al archive y desactivamos el chequeo de
# validez de las Release (allí están expiradas por definición).
RUN sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive

# Instala las dependencias necesarias
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establece el directorio de trabajo
WORKDIR /var/www

# Copia el código de la aplicación y ajusta los permisos
COPY . /var/www
RUN chown -R www-data:www-data /var/www

# Cambia la propiedad del directorio vendor
RUN mkdir -p /var/www/vendor && chown -R www-data:www-data /var/www/vendor

# Cambia al usuario www-data
USER www-data

WORKDIR /var/www

# Instala las dependencias de Composer
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Expone el puerto 9000 y arranca el servidor php-fpm
EXPOSE 9000
CMD ["php-fpm"]