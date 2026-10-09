# Fase 3C.1 V3: aprobación de antecedentes transitorios e integración pública

## Alcance y punto de integración

La fila persistente `solicitudes_contrato` identifica V3; su ausencia identifica los trámites V2 históricos. Ni la fecha ni la presencia de candidato determinan la versión. Las solicitudes nuevas son V3 transitorias. El controlador de `gestion-personas.reemplazos.approve` autoriza y deriva V3 a `AprobarAntecedentesTransitoriosAction`; V2 conserva `AprobarAntecedentesReemplazoAction`. La ruta comprueba que la versión de respaldo seleccionada pertenezca a la solicitud antes de invocar la acción. El guard central impide una transición V3 directa sin afectación y reserva coherentes.

El recorrido público V3 es: `reemplazos.store` crea el borrador, `reemplazos.respaldo-transitorio.store` registra un respaldo propio, `reemplazos.send` envía, `gestion-personas.reemplazos.start` inicia revisión, `gestion-personas.reemplazos.save` guarda antecedentes administrativos y `gestion-personas.reemplazos.approve` aprueba. El respaldo debe cubrir el período completo del funcionario y corresponder a su persona y unidad origen. Enviar e iniciar revisión vuelven a comprobarlo; ninguna de esas dos operaciones crea afectación ni reserva. El candidato es opcional en V3 y obligatorio en V2; si está presente, se validan sus antecedentes y período. El envío mantiene el historial normal.

La aprobación exige `EN_REVISION` y usa `APROBAR_ANTECEDENTES` hacia `LISTA_GENERAR_DOCUMENTO` dentro de la transacción. Este destino no habilita el PDF V2: la vista V3 informa que la generación documental está pendiente, la policy deniega la ruta HTTP, y el servicio documental y guard de transición rechazan su uso directo. El PDF V2 sigue disponible para solicitudes históricas autorizadas.

## Precondiciones y autorización

- El trámite es de tipo `REEMPLAZO`, tiene `solicitudes_contrato` con modalidad `TRANSITORIA` y un detalle de reemplazo íntegro.
- El trámite está en `EN_REVISION`. Un segundo intento desde el estado posterior se rechaza.
- El actor tiene cuenta activa, permiso `reemplazos.revisar` y alcance institucional sobre la solicitud, o `reemplazos.alcance_global`, según `TramiteReemplazoPolicy`. `tramites.ver_todos` no concede aprobación.
- Se informa el respaldo y el ID de la versión vista por el operador. Bajo bloqueo se exige que aún sea la última versión, que corresponda a funcionario y unidad origen, y que el período del funcionario quede dentro del respaldo.
- La consulta autoritativa de Fase 3B vuelve a comprobar que ningún otro respaldo del funcionario se superponga con la versión elegida.
- Si hay persona propuesta, su período debe estar completo, contenido en el período del funcionario y en la versión del respaldo. La reserva activa de esa persona no puede solaparse con otra. Sin candidato, no se exige `reemplazante_id` ni se crea reserva.
- No puede existir ya una afectación activa del respaldo ni de la solicitud. Los índices únicos de Fase 3A permanecen como barrera adicional.
- La revisión administrativa debe aportar grado, clasificación de área y decisión explícita sobre cumplimiento normativo; el motor existente lo verifica antes de transicionar.

## Operación atómica y bloqueo

La acción toma una lectura preliminar solo para identificar personas. `TransaccionPeriodosTransitorios` abre la transacción MySQL y bloquea las filas de todas las personas involucradas en orden ascendente. Después bloquea el trámite, solicitud, detalle, respaldo y última versión. Vuelve a comprobar los datos preliminares, el estado, la autorización, la versión, la cobertura y la exclusividad activa **después** de los bloqueos. Este orden es coherente con Fase 3B: persona antes de respaldo o reserva.

En la misma transacción crea la afectación, llama a `EscriturasPeriodosTransitorios::registrarReservaBajoBloqueo` si existe candidato, guarda y marca la revisión, y llama al motor central `TransicionarTramite`. La reserva reutiliza la validación inclusiva de Fase 3B. El guard de transición V3 verifica afectación y reserva activa contra la solicitud y el detalle. Cualquier error revierte afectación, reserva, revisión, estado e historial. La acción traduce colisiones de unicidad MySQL en un error controlado; el coordinador 3B reintenta deadlocks y comunica conflictos transitorios persistentes.

`TramiteHistorial` recibe del motor central la acción `APROBAR_ANTECEDENTES`, actor, fecha, estado anterior y posterior. Su metadata contiene `revision_id`, `respaldo_id`, `respaldo_version_id`, `afectacion_id` y, cuando hay candidato, `reserva_id`, `persona_reservada_id` y fechas inclusivas de reserva. El evento es inmutable. La afectación conserva el ID exacto de versión comprometida.

Los errores de validación distinguen solicitud no aprobable, respaldo ocupado o incoherente, versión desactualizada, período fuera de cobertura y persona no disponible. Falta de permiso o alcance produce `AuthorizationException`; errores SQL no reconocidos se propagan.

## Pruebas y límites

`AprobarAntecedentesTransitoriosTest` usa SQLite para reglas funcionales, historial, autorización y reversión completa. `IntegracionAprobacionTransitoriaV3Test` recorre las rutas HTTP con y sin candidato, conflictos, alcance y bloqueo documental. La suite V2 verifica que el candidato y su documento conserven sus reglas. `AprobarTransitorioMysqlConcurrenciaTest` usa una base MySQL 8.4/InnoDB aislada y vacía y procesos PHP con conexiones independientes: dos aprobaciones del mismo trámite y dos trámites con el mismo candidato. Verifica una sola confirmación en cada carrera y ausencia de aprobaciones parciales. Sin `RRHH_MYSQL_3C1_TEST_DATABASE`, se omite.

```bash
docker compose exec -e RRHH_MYSQL_3C1_TEST_DATABASE=gestion_rrhh_3c1_test_ejemplo -T app php artisan test tests/Feature/AprobarTransitorioMysqlConcurrenciaTest.php
```

El nombre de la base aislada debe comenzar con `gestion_rrhh_3c1_test_`. La prueba la migra y siembra; no usa una transacción envolvente. Nunca debe apuntar a la base de aplicación.

El formulario V3 permite registrar un respaldo en `BORRADOR` y la revisión muestra sus versiones aptas para aprobación. El alta de respaldo usa bloqueos por persona de Fase 3B y registra historial; no compromete el respaldo. La interfaz no implementa edición ni anulación del respaldo. Tampoco se implementan liberaciones, cambios de candidato posteriores a aprobación, rectificaciones de respaldos comprometidos, generación documental V3, devolución posterior a aprobación ni contratación permanente. Estas capacidades quedan para fases posteriores.
