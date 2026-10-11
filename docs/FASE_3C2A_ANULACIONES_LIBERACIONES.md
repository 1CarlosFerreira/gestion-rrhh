# Fase 3C.2A: anulaciones administrativas y liberaciones

## Decisión y catálogo

El catálogo inspeccionado en la rama feature/v2-base-limpia, commit 961ba99,
contenía BORRADOR, ENVIADA_GESTION_PERSONAS, EN_REVISION,
DEVUELTA_PARA_CORRECCION, LISTA_GENERAR_DOCUMENTO, DOCUMENTO_GENERADO y
FORMALIZADA. No existía un equivalente a ANULADA.

Con aprobación funcional explícita se incorpora ANULADA como terminal mediante
AnulacionesTransitoriasV3Seeder, usando los modelos de catálogo existentes.
ANULAR_SOLICITUD exige motivo y reemplazos.anular. Sus únicos orígenes son
BORRADOR, ENVIADA_GESTION_PERSONAS, EN_REVISION, DEVUELTA_PARA_CORRECCION y
LISTA_GENERAR_DOCUMENTO. No se crean transiciones salientes. El guard central
rechaza incluso una transición saliente agregada por configuración y exige que
la anulación tenga destino ANULADA y provenga del caso de uso transaccional.

No hay nuevas tablas ni migraciones, ni cambios a los estados históricos V2.
DatabaseSeeder incorpora el seeder idempotente. Para instalar el catálogo en
una base existente, ejecutar deliberadamente:

    docker compose exec -T app php artisan db:seed --class=AnulacionesTransitoriasV3Seeder

Esta entrega no ejecuta ese seeder sobre la base de aplicación ni altera datos
reales. Las pruebas lo ejecutan en bases aisladas.

## Responsables y primer envío

Antes del primer envío, el operador autorizado (jefatura o administrativo)
requiere cuenta activa, reemplazos.crear, reemplazos.anular y alcance operativo
sobre las tres unidades del contexto V3. Solo se admite BORRADOR sin evidencia
de envío: un estado posterior sin historial de envío se trata como inconsistencia.

Después del primer envío, se requieren reemplazos.anular y la facultad
reemplazos.anular_enviadas, sembrada exclusivamente para Gestión de Personas,
más alcance sobre el contexto o reemplazos.alcance_global. El rol Administrador
no recibe automáticamente esa facultad exclusiva; las cuentas con funciones
de Gestión de Personas pueden tener ambos roles. La autorización usa permisos,
no nombres de roles. tramites.ver_todos no concede ejecución ni alcance funcional.

El primer envío se obtiene de los eventos persistentes ENVIAR_A_GESTION_PERSONAS
y REENVIAR_A_GESTION_PERSONAS, ordenados por fecha e ID. submitted_at y el estado
actual no sustituyen ese historial. Volver a borrador/corrección no recupera la
facultad de anulación directa del operador.

## Acción atómica y auditoría

AnularSolicitudTransitoriaAction valida identidad REEMPLAZO V3 TRANSITORIA,
detalle existente, motivo de hasta 2000 caracteres, etapa previa al documento y
autorización. Rechaza cualquier documento generado o formalización existente,
aunque el estado fuese incoherente.

La lectura preliminar identifica funcionario, candidato y personas presentes en
los compromisos. El coordinador de Fase 3B bloquea personas en orden ascendente,
abre una transacción propia en MySQL y reintenta deadlocks hasta tres veces.
Después bloquea trámite, solicitud y detalle; revalida identidades, estado,
documentos y autorización. Bloquea respaldos en orden estable, afectaciones y
reservas. Una persona nueva no incluida en los locks preliminares exige reintentar.

Libera reservas vigentes y afectaciones vigentes con la misma fecha, actor y
motivo. El trámite sin compromisos se anula sin inventar filas. Invoca
TransicionarTramite dentro de esa transacción para estado, finalized_at e
historial. Cualquier fallo revierte todas las liberaciones, estado e historial.
La segunda anulación se rechaza.

El evento ANULAR_SOLICITUD conserva estados anterior/posterior, motivo, ejecutor,
fecha, IDs de afectaciones/reservas liberadas, primer envío y peticiones
relacionadas. El guard comprueba que no queden compromisos activos y que la
ejecución provenga de la acción coordinadora registrada como singleton.
El contexto en memoria se limpia en finally y no procede de datos del cliente.
Los registros históricos y las versiones de respaldo no se eliminan.

## Petición e interfaz

SolicitarAnulacionTransitoriaAction conserva el trabajo de la entrega parcial.
Registra SOLICITAR_ANULACION en historial inmutable, motivo, solicitante, fecha,
primer envío y resultado PENDIENTE. Exige permiso de creación y alcance después
del envío. No crea estado, no libera ni ejecuta una anulación.

El expediente muestra botones separados según autorización, confirmación
explícita y motivo obligatorio. Las rutas POST usan autenticación, CSRF y Form
Request; la acción vuelve a autorizar bajo bloqueo. El detalle muestra peticiones
y anulaciones con actor, fecha y motivo. La bandeja de Gestión de Personas marca
peticiones y permite acceder a solicitudes devueltas; ANULADA aparece entre
finalizados y su detalle es de consulta.

La resolución se deriva del evento de anulación del expediente; la petición
original sigue inmutable. Se enlazan sus IDs en el evento ejecutado. No se crea
un módulo de peticiones, rechazo o notificaciones. Un borrador excepcional con
historial de envío se consulta por el expediente; no se amplía la bandeja a todos
los borradores.

## Reutilización

La selección de respaldos aptos admite los propios y respaldos liberados con
afectación histórica de una solicitud ANULADA y evento ANULAR_SOLICITUD. Excluye
cualquier compromiso vigente y exige coincidencia de funcionario, unidad origen
y cobertura. La ruta de aprobación admite esa selección; la acción 3C.1 revalida
versión, períodos y exclusividad dentro de sus locks.

La nueva afectación tiene ID propio y conserva el respaldo y versión usados.
No se recicla la fila liberada ni se crea un respaldo duplicado. Las consultas
informativas del selector no sustituyen la validación transaccional.

## Documento, reserva y contrato

Anulación administrativa de solicitud, anulación formal de reserva posterior al
documento y modificación/término de contrato son operaciones distintas.
Esta fase solo habilita la primera antes del documento. Después, el respaldo
continúa comprometido, documento y snapshot permanecen intactos y no se modifican
contratos. La anulación formal de reserva requiere el procedimiento institucional
específico pendiente; no se habilita ruta pública.

La generación V3 sigue bloqueada. No se crean documentos ni etapas documentales
V3 artificiales. La regresión documental usa generación real V2 y verifica que
la ruta nueva no cambie PDF, snapshot, historial ni estado.

## Concurrencia y pruebas

AnularTransitorioMysqlConcurrenciaTest migra y siembra exclusivamente una base
vacía con prefijo gestion_rrhh_3c2_test_. Inicia procesos PHP y conexiones MySQL
independientes; el padre retiene un lock de persona, observa al menos dos
transacciones esperando en performance_schema.data_lock_waits y las libera.
Requiere SELECT sobre esa tabla de diagnóstico para el usuario de pruebas.

    docker compose exec -e RRHH_MYSQL_3C2_TEST_DATABASE=gestion_rrhh_3c2_test_BASE_NUEVA -T app php artisan test tests/Feature/AnularTransitorioMysqlConcurrenciaTest.php

Carreras cubiertas: doble anulación, anulación/aprobación, liberación/nueva
afectación del mismo respaldo y liberación/nueva reserva de la misma persona.
La aprobación puede ganar y luego anularse, o perder ante una anulación: en ambos
casos el resultado final es terminal y no quedan compromisos de la anulada.
Una aprobación de otra solicitud que pierde antes de liberar puede reintentarse;
se verifica una sola afectación/reserva activa y conservación de historia.

Pruebas funcionales cubren borrador sin compromisos, operador antes/después del
envío, Gestión de Personas, todos los estados admitidos, liberación con/sin
candidato, petición sin liberar, historial, reutilización pública, duplicados,
doble anulación, terminalidad, rollback tras liberar, permisos, alcance,
inactividad, modalidad permanente y rechazo V2. Incluyen regresiones 3C.1,
documentación V2 y roles, y verificación de idempotencia del catálogo.

## Límites

No se implementan cambio de candidato, rectificación, generación V3, términos
de contratos, DocDigital ni contratación permanente. Sin backfill, commit o push.
No hay nuevas decisiones funcionales pendientes para la anulación previa al
documento aprobada. Sigue pendiente el procedimiento posterior al documento.

## Resultados finales de validación

- Suite completa en Docker: 394 aprobadas, 3 omitidas, 2343 aserciones.
  Incluye las pruebas específicas (20 casos) y regresiones 3C.1 y V2.
  Las tres clases MySQL se omiten sin sus variables de entorno; la de 3C.2A
  se ejecutó por separado.
- Concurrencia 3C.2A MySQL 8.4: 1 caso con cuatro carreras, 57 aserciones,
  aprobado en gestion_rrhh_3c2_test_20261010_final. Usó usuario exclusivo
  rrhh_3c2_test; no necesitó acceso a la base de aplicación.
- Primera corrida completa detectó respuesta 404 en doble aprobación pública.
  Se corrigió para conservar el error de validación de estado y la suite final
  pasó. No se modificaron las expectativas de ese caso V3 existente.
- Laravel Pint --test: aprobado, 262 archivos.
- npm run build dentro del servicio node: aprobado.
- git diff --check: aprobado.
- Sin migraciones nuevas. Seeder nuevo AnulacionesTransitoriasV3Seeder;
  RolesPermisosSeeder y DatabaseSeeder actualizados.
- Git final: 19 archivos existentes modificados y 12 archivos nuevos sin
  seguimiento. No se hizo commit ni push.

## Auditoría final de Fase 3C.2A

Revisión realizada en Ubuntu sobre WSL2, rama feature/v2-base-limpia y base
961ba99. Se revisaron los 19 archivos modificados y los 12 nuevos: todos
corresponden a esta fase, sus regresiones y documentación. No se encontraron
archivos temporales, claves privadas, tokens evidentes ni marcadores de conflicto.
El worker de tests/Support es necesario para las conexiones independientes de
la prueba MySQL y no es un archivo temporal.

Se corrigió un defecto de instalación: el seeder podía dejar cambios parciales
si faltaba un catálogo requerido y updateOrCreate sobrescribía configuraciones
existentes de ANULAR_SOLICITUD. Ahora usa una transacción, firstOrCreate y rechaza
configuraciones incompatibles sin modificarlas. La caché de permisos se limpia
también al fallar. No sincroniza ni elimina permisos de roles y no altera
transiciones ajenas. Las pruebas verifican repetición, permisos personalizados,
conflictos y rollback ante catálogos incompletos.

La auditoría confirmó que ANULADA y ANULAR_SOLICITUD solo habilitan la operación
para REEMPLAZO V3 TRANSITORIA; las regresiones de V2 conservan generación, PDF,
snapshot e historial. Una nueva prueba HTTP modela deliberadamente un documento
existente con estado LISTA_GENERAR_DOCUMENTO y comprueba rechazo sin cambiar
estado, documento, historial, afectación ni reserva. Es una fixture de
inconsistencia, no una implementación de generación V3.

Las liberaciones, transición e historial participan en una única transacción;
la prueba de fallo después de liberar demuestra rollback. La segunda anulación,
el uso directo del motor sin la acción y una transición saliente artificial se
rechazan. Edición, revisión, aprobación, generación y formalización comprueban
estado/autorización en backend. El primer envío proviene del historial ordenado
por occurred_at e ID; submitted_at no lo sustituye. Se añadió comprobación HTTP
de permiso posterior al envío y de alcance, incluso con tramites.ver_todos.

Las tres pruebas omitidas en la suite predeterminada son exactamente:

| Clase y método | Variable requerida | Prefijo de base vacía |
|---|---|---|
| PeriodosMysqlConcurrenciaTest::test_two_independent_mysql_connections_serialize_backings_and_reservations | RRHH_MYSQL_TEST_DATABASE | gestion_rrhh_3b_test_ |
| AprobarTransitorioMysqlConcurrenciaTest::test_independent_approvals_serialize_by_request_and_candidate | RRHH_MYSQL_3C1_TEST_DATABASE | gestion_rrhh_3c1_test_ |
| AnularTransitorioMysqlConcurrenciaTest::test_real_cancellation_approval_reuse_and_reservation_races | RRHH_MYSQL_3C2_TEST_DATABASE | gestion_rrhh_3c2_test_ |

Se omiten explícitamente en setUp cuando falta una variable válida. Requieren
MySQL 8.4 y procesos/conexiones independientes: SQLite en memoria no reproduce
los bloqueos InnoDB. Cada prueba exige una base vacía y la migra/siembra; 3C.2A
también requiere SELECT en performance_schema.data_lock_waits. No se repitieron
esas carreras durante esta auditoría. El resultado MySQL descrito antes
corresponde a la validación previa y no a una ejecución nueva.

Para instalar únicamente esta fase en desarrollo, con las migraciones previas
y el catálogo Reemplazos V2 ya instalados:

    docker compose exec -T app php artisan db:seed --class=AnulacionesTransitoriasV3Seeder

Verificar que la conexión efectiva del contenedor apunta a desarrollo, incluida
cualquier configuración cacheada, y respaldar la base antes de instalar. No
usar DatabaseSeeder ni RolesPermisosSeeder como sustitutos: el segundo sincroniza
roles completos. Ante un catálogo incompatible, el seeder específico aborta y
revierte; revisar el conflicto antes de reintentar. No se ejecutó este comando
sobre la base de aplicación ni se crearon migraciones, commits o pushes.

Validación posterior a las correcciones de auditoría:

- docker compose exec -T app php artisan test --compact: 399 aprobadas,
  3 omitidas, 2360 aserciones; incluye cinco pruebas nuevas de auditoría.
- docker compose exec -T app ./vendor/bin/pint --test: aprobado, 262 archivos.
- git diff --check: aprobado.
- Git conserva 19 archivos modificados y 12 nuevos sin seguimiento.
  Esta auditoría solo cambió AnulacionesTransitoriasV3Seeder,
  AnularSolicitudTransitoriaTest y este documento.
