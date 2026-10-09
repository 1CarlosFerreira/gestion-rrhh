# Fase 3B V3: períodos y concurrencia

La Fase 3B agrega un motor interno sobre las tablas de la Fase 3A. No incorpora rutas, formularios ni transiciones administrativas. El comportamiento V2 sigue independiente.

## Servicios y reglas

- `PeriodosInclusivos` valida fechas `YYYY-MM-DD`, orden y contención. Los extremos representan días completos y son inclusivos.
- `ConflictosPeriodosTransitorios` consulta solapamientos de respaldos y reservas. Dos períodos entran en conflicto cuando `inicio_existente <= fin_nuevo` y `fin_existente >= inicio_nuevo`. Por ello compartir un extremo crea conflicto y comenzar al día siguiente está permitido.
- Para un respaldo se considera únicamente su última versión, por funcionario de origen, cualquiera sea su motivo o solicitud. Una afectación liberada no elimina el respaldo ni su participación en esta regla. La rectificación excluye solo el mismo respaldo.
- Para una reserva se consideran solo filas con `liberado_at IS NULL`, por persona propuesta. Una fila liberada permanece como antecedente histórico. La consulta admite excluir una reserva concreta para validaciones futuras.
- Los métodos `respaldo()` y `reserva()` son consultas informativas. Sus resultados pueden cambiar antes de una escritura. Los métodos `*BajoBloqueo()` exigen una transacción abierta; la protección efectiva también exige haber bloqueado antes la fila de `personas` correspondiente mediante el coordinador.

## Escrituras internas protegidas

`EscriturasPeriodosTransitorios` ofrece tres operaciones estructurales, sin controlador público:

1. `registrarRespaldo`: valida el período, la modalidad transitoria y la unidad de origen; crea padre y primera versión en una transacción.
2. `rectificarPeriodoRespaldo`: exige motivo, comprueba conflictos y crea una nueva versión sin modificar las anteriores. Rechaza respaldos con afectación activa porque su cobertura y reservas requieren rectificación coordinada en una fase posterior.
3. `registrarReserva`: exige una afectación activa, período contenido en la versión comprometida y ausencia de otra reserva activa superpuesta para la persona. Una solicitud sin candidato no genera reserva.

Estas son operaciones de persistencia estructural para uso interno y pruebas. No autorizan por sí mismas una acción de Gestión de Personas, ni formalizan contratos o modifican dotación.

## Transacción y orden de bloqueos

`TransaccionPeriodosTransitorios` abre una transacción propia y bloquea las filas estables de `personas` con `lockForUpdate()` en orden ascendente de ID. El mismo criterio debe usarse si una operación futura involucra a varias personas. En MySQL rechaza iniciar dentro de otra transacción, para evitar una vista `REPEATABLE READ` previa al bloqueo. Las consultas autoritativas ocurren después de tomar esos bloqueos. Bloquear solo reservas existentes dejaría desprotegido el caso sin reservas previas.

Después de la persona se bloquean las entidades asociadas, cuando corresponda: solicitud para crear respaldo; respaldo y versión actual para rectificar; respaldo y afectación para reservar. No se usan bloqueos globales. Todas las escrituras y sus validaciones comparten la transacción, por lo que un conflicto revierte la operación completa. Los índices únicos activos de Fase 3A siguen como barrera adicional para sus invariantes de exclusividad.

Los conflictos de negocio se expresan como `ValidationException`. La transacción reintenta hasta tres veces los deadlocks detectados por Laravel y transforma deadlocks o esperas de bloqueo agotadas en un error recuperable de validación si persisten. La reserva traduce además conflictos de unicidad de la base de datos. Otros errores SQL se propagan.

La garantía de no superposición entre respaldos o reservas depende de que **todas** las futuras escrituras y rectificaciones de períodos de una misma persona utilicen este protocolo de bloqueo, incluida cualquier liberación y nueva reserva coordinada. Los índices actuales no representan unicidad de intervalos arbitrarios y no reemplazan este contrato. La consulta informativa nunca autoriza una escritura por sí sola.

## Pruebas y límites

`PeriodosTransitoriosMotorTest` cubre reglas y operaciones secuenciales con SQLite. `PeriodosMysqlConcurrenciaTest` abre procesos PHP con conexiones MySQL 8.4/InnoDB independientes y compite por la misma persona en dos carreras: respaldo y reserva. Verifica que se confirma una operación, la otra informa conflicto, no quedan registros parciales y puede escribirse después de liberar el bloqueo.

La prueba MySQL requiere una base **aislada y vacía**, cuyo nombre comience con `gestion_rrhh_3b_test_`; por ejemplo:

```bash
docker compose exec -e RRHH_MYSQL_TEST_DATABASE=gestion_rrhh_3b_test_ejemplo -T app php artisan test tests/Feature/PeriodosMysqlConcurrenciaTest.php
```

La prueba ejecuta migraciones y seeders en esa base y no usa una transacción envolvente. Sin esa variable se omite; una prueba SQLite no constituye prueba de concurrencia real.

La Fase 3C deberá llamar a estas operaciones desde casos de uso autorizados, dentro del contrato transaccional, para aprobar antecedentes, incorporar candidato y coordinar cambios de persona o período con liberaciones e historial. También deberá decidir y validar por separado las reglas aplicables a contratación formalizada y dotación: ninguna equivale a una reserva. La rectificación de un respaldo ya afectado requiere una operación nueva que preserve la consistencia de cobertura y reservas.
