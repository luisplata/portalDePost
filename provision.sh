#/bin/bash

apt-get update

#Este es el script para cuando se inicialice una maquina virtual cree todo
##instalamos todas las dependencias de laravel
#vamos al directorio del proyecto
cd /vagrant
#Instalamos las dependiencias
composer install
#copiamos el .env-example a .env
cp .env-example .env
#cambiamos la key app
php artisan key:generate
#corremos las migraciones
php artisan migrate