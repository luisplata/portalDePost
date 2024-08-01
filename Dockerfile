# Usa una imagen base adecuada
FROM php:7.4-fpm

# Instala las dependencias necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql

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