# Instalacion

Se utiliza Docker para la instalacion del ambiente de desarrollo.


Luego de correr el `up --build` del docker

entrar en el contenedor de `app` para correr los siguientes comandos:

Recordar que debes estar en el directorio `/var/www` para correr el comando

- `cp .env.example .env` para copiar y crear el archivo de enviroment del proyecto
- `composer install` 
- `php artisan key:generate` para generar la llave de seguridad de la app
- `php artisan migrate` para correr las migraciones de base de datos; si aparecen errores validar usuarios y contrasenias del archivo `.env` anteriormente copiados


Y eso seria todo. 
Deberias poder ver el index si vas a `http://localhost`