# Sistema de Gestión de Trámites y Documentos de RRHH

Infraestructura base del sistema interno del Hospital de Illapel. Usa Laravel 12, PHP 8.3 FPM, MySQL 8, Nginx y Vite/Tailwind, íntegramente mediante Docker Compose sobre WSL2.

## Requisitos

- WSL2 con Ubuntu (no la distribución `docker-desktop`).
- Docker con Compose e integración habilitada en la distribución.
- Puertos 80, 3306 y 5173 disponibles. Se pueden cambiar con `APP_PORT`, `DB_PORT_FORWARD` y `VITE_PORT`.

## Primer arranque

El servicio `app` crea `.env` desde `.env.example`, genera `APP_KEY`, instala Composer cuando falta `vendor` y prepara `storage` y `bootstrap/cache` sin usar permisos `777`.

```bash
docker compose config
docker compose up -d --build
docker compose ps
```

La aplicación queda en <http://localhost>. MySQL y Vite se enlazan exclusivamente a `127.0.0.1`.

## Operación habitual

```bash
docker compose up -d
docker compose up -d --build
docker compose down
```

Para eliminar también los volúmenes de desarrollo, incluida la base de datos, se debe ejecutar conscientemente `docker compose down -v`.

Dependencias PHP, Artisan, pruebas y formato:

```bash
docker compose exec app composer install
docker compose exec app php artisan --version
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Frontend dentro del contenedor Node:

```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
docker compose up -d node
docker compose logs -f node
```

Estado, logs y shells:

```bash
docker compose ps
docker compose logs
docker compose logs -f app nginx db node
docker compose exec app sh
docker compose exec node sh
```

## Configuración

`.env` es local y no se versiona. `.env.example` usa la red de Compose (`DB_HOST=db`) y valores solo aptos para desarrollo. Producción debe aportar secretos propios y configurar al menos:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://url-institucional.example
```

PHP y Laravel usan `America/Santiago`. Los documentos de RRHH deberán mantenerse en almacenamiento privado, sin enlaces desde `public/storage`.

### Acceso de desarrollo

Después de ejecutar `php artisan migrate:fresh --seed`, se crea exclusivamente para desarrollo:

```text
Usuario: admin@example.test
Contraseña: password
```

Estas credenciales son ficticias y deben reemplazarse en cualquier entorno distinto de desarrollo.

## Alcance actual

Fase 1: autenticación Blade, roles/permisos y catálogos base. No incluye personas, trámites ni procesos funcionales.
