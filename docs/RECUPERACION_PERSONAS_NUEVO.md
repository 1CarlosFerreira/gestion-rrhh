# Recuperación controlada de `nuevo.sql`

## Alcance y fuente

El comando `recuperacion:personas` **lee** `database/recuperacion/nuevo.sql`; nunca ejecuta sus sentencias SQL. Solo interpreta filas de `personas`, `persona_unidad_vinculos` y los catálogos laborales necesarios. No importa `users`, accesos operativos, roles, permisos, trámites ni historial. `DROP`, `TRUNCATE` y otras sentencias del respaldo no se ejecutan.

El respaldo original y los archivos JSON de cotejo/reporte están excluidos de Git. No agregar copias con otro nombre o ubicación al repositorio.

Análisis del respaldo examinado (SHA-256 `7064faf96ba870370f5d63505ec6b1ab0b2ca3c920993b2462e3bc2fa41edbe3`): 24 tablas; 10 personas; 9 vínculos manuales que refieren cuatro IDs de unidad (`42`, `44`, `52`, `57`). Hay 8 estamentos, 35 profesiones y 8 calidades contractuales en el respaldo. No están las tablas `unidades_organizacionales`, `users` ni `user_unidad_accesos`. Los nueve vínculos carecen de `origen_tramite_id`; sus personas de origen están presentes. Los códigos y nombres de catálogos que usan los vínculos coinciden con los catálogos actuales al momento del análisis.

Las columnas de `personas` y `persona_unidad_vinculos` del respaldo coinciden con las migraciones actuales para esas tablas. Se comparan usando los campos del esquema actual, sin conservar los IDs antiguos. Los RUT se normalizan y validan con `App\Support\Rut\Rut`. Una persona actual se conserva si RUT, nombres, apellidos y estado coinciden. Cualquier diferencia queda pendiente para revisión; no se actualiza la fila existente.

## Simulación inicial

```bash
docker compose exec -T app php artisan recuperacion:personas --dry-run
```

El reporte se guarda en `database/recuperacion/reporte-recuperacion.json` (excluido de Git) con conteos, SHA-256 y conflictos por tipo, ID de origen y motivo. Para conflictos de unidad incluye el ID histórico de unidad; no contiene RUT ni nombres. La simulación no escribe en la base de datos.

Con la base observada en el análisis: 9 personas por crear, 1 por conservar, 0 conflictos de persona; 0 vínculos por crear, 0 por conservar y 9 pendientes por unidad sin correspondencia verificada. Los conteos pueden cambiar si cambia la base actual; se debe repetir la simulación antes de aplicar.

## Cotejo de unidades

El SQL no contiene los códigos ni nombres de las unidades antiguas. Un ID antiguo igual a uno actual **no demuestra equivalencia**. Para cada uno de los cuatro IDs referidos, cotejar una fuente institucional fiable que documente la unidad original, su código/nombre y su correspondencia con la unidad actual. Registrar el origen de la evidencia fuera de Git. Si una equivalencia no puede acreditarse, dejar los vínculos de esa unidad pendientes.

Crear `database/recuperacion/unidades-verificadas.json` fuera de Git con este formato (valores ilustrativos, **no** son una correspondencia aprobada):

```json
{
  "dump_sha256": "7064faf96ba870370f5d63505ec6b1ab0b2ca3c920993b2462e3bc2fa41edbe3",
  "unidades": {
    "ID_ANTIGUO": {
      "codigo": "CODIGO_ACTUAL",
      "nombre": "Nombre exacto de la unidad actual",
      "evidencia": "Referencia del cotejo institucional"
    }
  }
}
```

El comando comprueba el SHA-256 del respaldo, el código y nombre exactos de la unidad actual, los catálogos por código y nombre, la compatibilidad de profesión, el periodo y la ausencia de solapamiento de un vínculo equivalente. El campo `evidencia` registra la revisión humana; el programa no puede comprobar por sí solo la identidad de la unidad antigua porque esa tabla falta en el SQL. No se debe inventar una correspondencia.

Con el archivo de cotejo revisado, volver a simular:

```bash
docker compose exec -T app php artisan recuperacion:personas --dry-run --unidad-map=database/recuperacion/unidades-verificadas.json
```

### Alternativa de cotejo técnico para desarrollo

La revisión local no encontró un exportado de `unidades_organizacionales` del sistema anterior ni un registro que una explícitamente los IDs antiguos con códigos. El seeder `fedbf21` del 2026-09-03 precede a los vínculos del respaldo y define los cuatro códigos candidatos. Los cargos de esos vínculos son coherentes con los nombres actuales, pero no prueban que los IDs antiguos tuvieran esos códigos.

`database/recuperacion/unidades-cotejo-manual-desarrollo.json` registra el cotejo técnico, los cuatro candidatos, sus indicios y la advertencia. Está excluido de Git. El comando solo lo acepta para simulación con `--mapa-manual-desarrollo` en `APP_ENV=local` o `testing`; rechaza `--apply` con ese mapa en cualquier entorno. El reporte conserva el tipo de mapa, la procedencia y la advertencia.

```bash
docker compose exec -T app php artisan recuperacion:personas --dry-run --mapa-manual-desarrollo --unidad-map=database/recuperacion/unidades-cotejo-manual-desarrollo.json --report=database/recuperacion/reporte-desarrollo-manual.json
```

El conteo de vínculos de esta simulación es **hipotético para desarrollo**. No convierte el mapa técnico en verificación histórica ni autoriza su aplicación. El archivo `unidades-verificadas.json` permanece vacío hasta recibir una confirmación externa de la equivalencia ID-código y jerarquía.

## Aplicación posterior, sujeta a autorización

El siguiente comando **no se ejecutó** en la base actual:

```bash
docker compose exec -T app php artisan recuperacion:personas --apply --actor=1 --unidad-map=database/recuperacion/unidades-verificadas.json
```

La cuenta 1 estaba activa, era Administrador y tenía `dotacion.gestionar` al momento del análisis; volver a comprobarlo antes de aplicar. La aplicación inserta únicamente personas nuevas y vínculos resolubles dentro de una transacción. Los vínculos nuevos reciben origen `IMPORTACION`, el actor actual en `created_by` y una observación de procedencia con el SHA-256 y el ID del vínculo antiguo. No copia `created_by` antiguo ni los IDs de persona/unidad/catálogos. Los registros existentes quedan intactos. Los conflictos permanecen en el reporte para decisión manual. Una reejecución con los mismos datos conserva los ya insertados.

No interpretar un vínculo de dotación recuperado como contrato V3, reserva de persona o destinación funcional. La recuperación no crea ni modifica esas entidades. Tampoco permite inferir situaciones administrativas o remuneraciones desde datos faltantes.

## Verificaciones antes de autorizar

1. Confirmar que el SHA-256 del respaldo y la base objetivo corresponden a los analizados.
2. Revisar la evidencia externa de las cuatro correspondencias de unidad; no basarse en IDs.
3. Ejecutar la simulación con el mapa y revisar todos los conflictos y conteos.
4. Confirmar respaldo de la base actual y ausencia de procesos concurrentes de edición de personas/dotación durante la aplicación.
5. Confirmar la cuenta de auditoría y su permiso.

La ausencia de la tabla antigua de unidades impide declarar recuperables los nueve vínculos sin ese cotejo. No existe en este SQL información que permita reconstruir cuentas o accesos operativos; quedan fuera de esta recuperación.
