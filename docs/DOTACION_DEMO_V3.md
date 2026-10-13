# Dotación ficticia V3 para desarrollo

`DotacionDemoV3Seeder` carga un conjunto reproducible de datos **inventados** para probar dotación y solicitudes transitorias V3. Es independiente de la recuperación histórica desde `nuevo.sql`; no utiliza ese respaldo, sus mapas ni sus identificadores de unidad. No forma parte de `DatabaseSeeder` y nunca se ejecuta automáticamente.

## Contenido

Los RUT se generan de forma determinista a partir de los cuerpos `90000001` a `90000012`, con dígito verificador válido. Todos los nombres y apellidos son inventados. Las unidades se buscan exclusivamente por `codigo` institucional.

| Unidad | Personas con vínculo vigente desde 2025-01-01 | Personas con vínculo histórico 2024-01-01 a 2024-12-31 |
|---|---|---|
| `SDGADM-INF` — Unidad de Informática | Ardelia Nivar, Belorio Talen, Cerina Vorel, Dariol Nemer | Karelia Tenvar |
| `SDGDP-DGP-GP` — Unidad Gestión de las Personas | Eloria Mavel, Fareno Velis, Girela Nolven | Loriane Savel |
| `SDGADM` — Subdirección de Gestión Administrativa | Halaro Fenrel, Ivelia Rolden, Junerio Falven | — |

Se crean **12 personas y 12 vínculos**: diez vigentes y dos finalizados. Los vínculos usan los catálogos ya cargados para estamento, profesión y calidad contractual; no inventan grados E.U.S. La procedencia queda en la observación `Datos ficticios V3: DotacionDemoV3Seeder`.

Se crean tres titularidades ficticias con `puede_aprobar`: Ardelia en Informática, Eloria en Gestión de las Personas y Halaro en la Subdirección Administrativa. Sus períodos están cubiertos por su dotación. Estas personas no reciben cuenta automáticamente.

| Cuenta de prueba | Persona | Rol | Accesos operativos |
|---|---|---|---|
| `demo.v3.solicitante@example.test` | Fareno Velis | `Solicitante` | `SDGADM` y descendientes; `SDGDP-DGP-GP` solo unidad |
| `demo.v3.gestion@example.test` | Girela Nolven | `Gestión de Personas` | `SDGADM` y descendientes; `SDGDP-DGP-GP` solo unidad |

Los dos accesos de cada cuenta comienzan el 2025-01-01. El rol y los accesos permiten preparar una solicitud transitoria con Subdirección Administrativa como solicitante, Informática como origen y Gestión de las Personas como destino. Belorio puede actuar como funcionario de origen vigente; Karelia y Loriane permiten examinar personas con dotación histórica. El seeder no crea trámites, solicitudes, respaldos ni historiales.

## Requisitos y ejecución en cada computador

1. Trabajar en la copia local del repositorio con Docker Compose y una base de **desarrollo**, con `APP_ENV=local` o `testing`. Deben existir las migraciones y los catálogos, roles y la estructura institucional habituales del proyecto, incluidas las tres unidades activas. El seeder se detiene si falta alguna dependencia; no la crea ni la modifica.
2. Desde la raíz del proyecto en WSL2, ingresar una contraseña de prueba de al menos 12 caracteres cuando se solicite y ejecutar:

```bash
read -r -s -p 'Contraseña de las cuentas demo V3: ' RRHH_DEMO_PASSWORD; printf '\n'
export RRHH_DEMO_PASSWORD
docker compose exec -T -e RRHH_ALLOW_DEMO_SEEDING=1 -e RRHH_DEMO_PASSWORD app php artisan db:seed --class=DotacionDemoV3Seeder
unset RRHH_DEMO_PASSWORD
```

Usar la misma secuencia en ambos computadores. Docker Compose recibe la variable desde el entorno local sin poner su valor en los argumentos del comando. La contraseña no se guarda en Git ni en el archivo `.env`; puede ser distinta en cada equipo. Si las cuentas demo ya existen, el seeder conserva su contraseña original. Para cambiarla hay que usar el flujo normal de administración de usuarios.

El seeder exige simultáneamente que el entorno real de Laravel y `config('app.env')` sean `local` o `testing`, y el opt-in explícito `RRHH_ALLOW_DEMO_SEEDING=1`. También exige la contraseña temporal. Rechaza personas o cuentas con identificadores reservados que no tengan procedencia demo verificable. Todas sus escrituras se realizan en una transacción; ante una colisión o validación fallida no quedan cambios parciales. Una segunda ejecución conserva los registros existentes y no sobrescribe contraseñas, roles, accesos ni vínculos.

No ejecutar esta carga en una base compartida o con información real. La protección de entorno evita su uso en producción, pero `APP_ENV=local` por sí solo no garantiza que una base contenga únicamente datos de desarrollo. La recuperación histórica desde `nuevo.sql` requiere su propio procedimiento y autorización; este seeder no la sustituye.

## Verificación automatizada

```bash
docker compose exec -T app php artisan test tests/Feature/DotacionDemoV3SeederTest.php
docker compose exec -T app ./vendor/bin/pint --test
```

Las pruebas usan SQLite en memoria y no ejecutan el seeder sobre la base MySQL configurada para desarrollo.
