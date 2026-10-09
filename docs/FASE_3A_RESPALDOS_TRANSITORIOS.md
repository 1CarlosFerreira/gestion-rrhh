# Fase 3A V3: estructura de respaldos transitorios

Esta fase agrega solo el modelo persistente. No conecta la aprobación,
anulación, cambio de persona, rectificación ni generación documental a
las tablas nuevas. Las reglas funcionales son las RF-01 a RF-18 de
`MAPA_FUNCIONAL_V3.md`.

## Tablas y significado

| Tabla | Identidad y relación | Historia conservada |
|---|---|---|
| `respaldos_transitorios` | ID numérico y ULID interno único; `solicitud_origen_id` apunta a `solicitudes_contrato`; `created_by` identifica al registrador. | El registro padre no se edita ni elimina desde Eloquent. Puede tener varias afectaciones en distintos momentos. |
| `respaldo_transitorio_versiones` | Cada fila contiene funcionario y unidad origen, motivo, fechas inclusivas, referencia externa opcional y actor. `respaldo_id` + `version` es único. | Cada rectificación futura agrega una versión completa con motivo, actor y fecha. La versión anterior permanece; la primera conserva los datos originales. |
| `respaldo_afectaciones` | Vincula respaldo, versión usada y solicitud transitoria; registra compromiso, actor y liberación opcional. La FK compuesta impide referir una versión de otro respaldo. | Cada compromiso y liberación permanece en una fila propia. Una nueva afectación puede usar el mismo respaldo liberado. |
| `reserva_persona_periodos` | Vincula una afectación con persona, fechas inclusivas, actor y momento de reserva; no exige que toda afectación tenga reserva. | Las reservas liberadas permanecen al cambiar de persona. No representan contrato, destinación ni dotación. |

El motivo del respaldo es texto obligatorio porque el catálogo transitorio
definitivo sigue pendiente. La referencia administrativa externa es
opcional y no tiene índice único: ni su ausencia ni su igualdad
demuestran la identidad administrativa del antecedente. El ULID interno
identifica el registro local, no un acto externo.

`solicitudes_contrato` mantiene la modalidad y el contexto organizacional;
`tramites` sigue siendo la raíz del expediente. Los modelos rechazan
crear respaldos o afectaciones para modalidad `PERMANENTE`. Las FK
comprueban existencia, pero la modalidad no tiene todavía una
restricción cruzada de base de datos: una escritura SQL que omita
Eloquent debe validar este invariante. No se modifican tablas V2 ni se
crean respaldos retrospectivos.

## Exclusividad activa

`liberado_at IS NULL` define una afectación o reserva `VIGENTE`; una
fecha de liberación la deja `LIBERADA`. El estado se deriva de ese dato,
sin una segunda columna que pueda discrepar. Fecha, responsable y motivo
de liberación forman un conjunto obligatorio; MySQL 8.4 aplica `CHECK`
y los modelos lo validan al guardar.

La afectación tiene dos columnas virtuales: `respaldo_activo_id` y
`solicitud_activa_id`. Cada una vale su FK mientras `liberado_at` es
`NULL` y pasa a `NULL` al liberar. Sus índices únicos impiden dos
afectaciones activas del mismo respaldo o de la misma solicitud.
MySQL permite varios `NULL` en un índice único, por lo que conserva
todas las afectaciones liberadas. La reserva usa el mismo mecanismo con
`afectacion_activa_id`: solo una reserva vigente por afectación, sin
impedir conservar las anteriores. No existe `UNIQUE(respaldo_id)`.

La versión del respaldo referida por cada afectación permanece
inmutable. Esto conserva los datos del compromiso incluso si una
rectificación posterior agrega otra versión. Un futuro documento debe
guardar también su propio snapshot y versión; esta fase no lo genera.

## Integridad implementada y límites

- Las FK son restrictivas al eliminar y los modelos impiden borrar o
  alterar identidad, versiones y registros históricos. Afectaciones y
  reservas solo admiten completar una liberación una vez. Escrituras SQL
  directas con privilegios de actualización pueden evitar estas reglas
  de Eloquent; la auditoría operativa será responsabilidad de Fase 3B.
- MySQL verifica versiones positivas y `fecha_desde <= fecha_hasta` para versiones y reservas,
  y que la liberación completa no anteceda al compromiso o reserva.
  SQLite de pruebas carece de esos `CHECK` en esta migración; los modelos
  comprueban fechas y liberaciones al guardar.
- Las FK, índices y columnas generadas no impiden superposiciones entre
  rangos de distintos respaldos o distintas afectaciones. Tampoco
  comprueban que la cobertura quede contenida en el respaldo ni que
  funcionario y unidad coincidan con otros antecedentes de la solicitud.
- El esquema permite crear un padre antes de insertar su versión inicial
  y no impone secuencia sin saltos de versiones. Fase 3B deberá crear
  ambos registros en una transacción y bloquear el respaldo al numerar
  versiones.

Los intervalos son días calendario inclusivos: hay conflicto cuando
`inicio_a <= fin_b` y `inicio_b <= fin_a`. Períodos consecutivos sin día
común están permitidos. Fase 3B deberá revalidar dentro de la misma
transacción al registrar, aprobar, reservar, cambiar, reutilizar o
rectificar. Para evitar carreras, deberá bloquear en orden estable las
filas de persona funcionaria, persona propuesta, respaldo y solicitud
pertinentes antes de consultar solapamientos, incluso cuando aún no
exista una fila de reserva; también deberá resolver reintentos de
deadlock. Las claves únicas actuales son una última barrera para
exclusividad por identidad, no sustituyen esa validación de rangos.

## Verificación

`RespaldoTransitorioEstructuraTest` usa `RefreshDatabase` con la
conexión SQLite `:memory:` de `phpunit.xml`: migra desde cero y cubre
identidad, FK, relaciones, versiones, historial, liberación y
reutilización, unicidad activa, fechas, modalidad y ausencia de candidato.
Las pruebas no afirman que ya exista un flujo de aprobación, reserva
automática, anulación ni control transaccional de superposiciones.
