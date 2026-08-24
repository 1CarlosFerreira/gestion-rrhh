# AGENTS.md - Sistema de Gestión de Trámites y Documentos de RRHH

## Contexto
Proyecto interno del Hospital de Illapel para gestionar trámites administrativos de RRHH. El sistema es multiproceso y parte con Reemplazos y Horas Extraordinarias.

## Antes de modificar código
1. Lee `MVP.md` y la documentación funcional/técnica vigente.
2. Ejecuta `git status`, `git branch --show-current`, `git log -1 --oneline` y `git remote -v`.
3. Confirma que estás trabajando desde WSL2 y no dentro de la distribución `docker-desktop`.
4. No sobrescribas cambios existentes que no hayas creado.
5. Trabaja solo en el alcance solicitado de la fase actual.

## Entorno
- Todo el proyecto se ejecuta en Docker Compose.
- No instalar PHP, Composer, Node, MySQL ni Nginx directamente en Windows.
- Los comandos de PHP/Composer/Artisan se ejecutan dentro del contenedor de aplicación.
- Los comandos npm se ejecutan dentro del contenedor Node definido por el proyecto.
- Timezone de aplicación: `America/Santiago`.

## Arquitectura
- Monolito modular Laravel.
- `tramites` es la raíz de procesos.
- Datos específicos en tablas/modelos por tipo de trámite.
- Controladores pequeños.
- Validación en Form Requests.
- Autorización en Policies/Gates.
- Reglas/casos de uso en `app/Actions` o `app/Services`.
- No introducir Repository Pattern, CQRS, DDD pesado ni microservicios sin requerimiento explícito.

## Base de datos
- MySQL 8, utf8mb4.
- FKs e índices explícitos.
- No usar MySQL ENUM para estados de trámites.
- Catálogos se desactivan en vez de eliminarse cuando hayan sido usados.
- RUT único y normalizado.
- Horas extraordinarias se almacenan en minutos enteros.
- No guardar archivos como BLOB.

## Estados e historial
- Ningún controlador debe asignar `estado_tramite_id` directamente.
- Todas las transiciones pasan por un servicio/Action central que:
  1. valida transición,
  2. valida permiso,
  3. valida condición/observación,
  4. cambia estado dentro de transacción,
  5. registra `tramite_historial`.
- El historial es inmutable desde la aplicación.

## Archivos
- Storage privado, nunca `public/storage` para documentos de RRHH.
- Nombre físico aleatorio/ULID/UUID.
- Guardar nombre original, MIME, tamaño, SHA-256, usuario, fecha y versión.
- Descarga solo mediante controlador autorizado.
- Una corrección crea nueva versión; no borrar la anterior.
- Planilla SIRH: PDF. No OCR ni extracción automática en el MVP.

## Seguridad
- Autorización en backend para cada lectura/escritura sensible.
- No confiar en campos `user_id`, `unidad_id`, rol o estado enviados por el cliente sin validar.
- CSRF activo.
- No registrar contenido sensible en logs.
- Datos de fixtures/tests siempre ficticios.

## Código y estilo
- PSR-12 / Laravel Pint.
- Nombres de clases y métodos descriptivos en inglés o español consistente; preferencia: dominio en español si coincide con la BD/documentación (`Tramite`, `Unidad`, `Persona`).
- Códigos técnicos de estado/acción en MAYÚSCULAS_CON_GUION_BAJO para estados y `namespace.accion` para permisos.
- Evitar lógica de negocio en Blade.
- Evitar consultas N+1; usar eager loading en bandejas/detalles.
- No optimizar prematuramente ni crear abstracciones para procesos aún no definidos.

## Pruebas mínimas por fase
- Permisos/Policies afectados.
- Transiciones válidas e inválidas.
- Reglas críticas de la fase.
- Archivos privados/versionado cuando aplique.
- `php artisan test` para suite relevante.
- `./vendor/bin/pint --test` antes de cerrar fase.

## Pendientes que NO debes inventar
- Grados E.U.S. oficiales.
- Documentos obligatorios por tipo de reemplazo.
- Plantilla definitiva de Reemplazo.
- Correcciones ortográficas de catálogos entregados.
- Integraciones automáticas con SIRH o DocDigital.
- Firma electrónica interna.

## Entrega al terminar una fase
Reporta:
- qué implementaste,
- archivos principales cambiados,
- migraciones/seeders creados,
- pruebas/comandos ejecutados y resultado,
- decisiones tomadas,
- pendientes o bloqueos,
- `git status` final.
No avances a la fase siguiente sin una nueva instrucción.
