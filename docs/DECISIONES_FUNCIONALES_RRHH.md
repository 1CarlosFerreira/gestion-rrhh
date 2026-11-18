# Decisiones funcionales de Gestión RRHH

**Referencia:** 18-11-2026. **Estado:** consolidación propuesta para aprobación humana. Este documento no autoriza implementación ni reemplaza el README como descripción del código vigente.

## Procedencia y alcance

El responsable humano comunicó y aprobó expresamente en la solicitud de esta entrega la correspondencia P0/P1 registrada abajo. El [mapa funcional V3](MAPA_FUNCIONAL_V3.md) aporta contexto de dominio; el [README](../README.md) describe el código vigente. Si una regla nueva difiere de la implementación o de documentación anterior, queda registrada como decisión funcional pendiente de integración, sin atribuirle funcionamiento actual.

## Reglas aprobadas comunicadas por el responsable

| Tema | Decisión funcional | Estado respecto del código actual |
|---|---|---|
| Plataforma | Evolucionar hacia una plataforma institucional de solicitudes; Gestión RRHH es el primer módulo. | Visión futura. Existe el núcleo transversal de trámites, pero no una plataforma con otros módulos funcionales. |
| Responsabilidades | Solicitante y Jefatura son responsabilidades distintas. | P1a introdujo cambios de rol y autorización para Solicitante en Reemplazos; no acredita por sí sola toda la separación funcional futura. |
| Alcance | La solicitud exige autorización sobre la unidad solicitante; pueden seleccionarse otras unidades relacionadas sin acceso administrativo a ellas. | El README actual describe validación de acceso operativo a las tres unidades para crear V3. La regla P0-01 requiere adaptación; no se declara implementada. |
| Candidato | El candidato propuesto es opcional; Gestión de Personas puede seleccionarlo administrativamente. Se admite aprobar sin candidato. | V3 permite envío y aprobación sin candidato. La selección administrativa posterior y sus efectos requieren cotejo de alcance y no se declaran implementados íntegramente. |
| Datos | Conservar separados los datos originales de la solicitud y los datos administrativos de revisión o resultado. | Existen revisión e historial; la separación integral del resultado final V3 sigue pendiente. |
| Correcciones | Permitir devolución, corrección y reenvío con trazabilidad; conservar versiones históricas de solicitudes. | V2 tiene ciclos de devolución y reenvío e historial. Hay versiones de respaldos y adjuntos. No consta un versionado integral de la solicitud V3 ni la devolución posterior a aprobación. |
| Aprobación | Bloquear la edición normal después de aprobar. | La aprobación V3 compromete respaldo y reserva cuando corresponde. Las correcciones posteriores requieren un flujo administrativo específico todavía pendiente. |
| Documento | Generar el memorándum después de la revisión administrativa. | V2 genera un PDF después de aprobar antecedentes. La generación documental V3 está bloqueada hasta definir la autoridad del documento y su contenido. No se equipara automáticamente ese PDF al memorándum definitivo. |
| Sistemas externos | Gestión de Personas continúa la tramitación en DocDigital. DocDigital y SIRH tienen seguimiento independiente. Puede evaluarse una importación futura desde SIRH. | No hay integración funcional con esos sistemas. La formalización V2 es un registro manual; los datos externos y sus estados requieren definición institucional. |

## Matriz P0 aprobada

| ID | Decisión humana aprobada | Implementación existente o límite |
|---|---|---|
| P0-01 | El Solicitante requiere autorización sobre la unidad solicitante y puede seleccionar otras unidades relacionadas sin acceso administrativo a ellas. | Pendiente de integrar: el flujo V3 actual exige acceso operativo a las tres unidades. |
| P0-02 | Gestión de Personas puede seleccionar un candidato distinto, conservando la propuesta original y registrando el motivo. | La corrección administrativa de antecedentes del candidato existe en revisión; no se acredita la sustitución completa de candidato con preservación de propuesta y motivo. |
| P0-03 | El Solicitante puede corregir todos los antecedentes de su competencia cuando la solicitud está devuelta; cada reenvío conserva una versión histórica. | Existen devolución, corrección, reenvío e historial. El versionado integral de la solicitud por reenvío sigue pendiente. |
| P0-04 | Cualquier Solicitante autorizado de la misma unidad puede continuar una solicitud. | Requiere verificar y completar la autorización por unidad; no se atribuye implementación integral. |
| P0-05 | Gestión de Personas puede revisar y tramitar todas las solicitudes de reemplazo del establecimiento. | Existe revisión con permisos y alcance; la cobertura global completa de esta regla debe verificarse y, si corresponde, adaptarse. |
| P0-06 | Una solicitud aprobada no admite edición normal; cambios posteriores requieren rectificación o anulación controlada. | La aprobación V3 compromete antecedentes. La rectificación o anulación posterior al documento permanece pendiente. |
| P0-07 | Gestión de Personas puede seleccionar candidato antes de aprobar o incorporarlo después de una aprobación sin candidato. | V3 admite aprobar sin candidato; la incorporación administrativa posterior no se declara implementada. |

## Matriz P1 aprobada

| ID | Decisión humana aprobada | Implementación existente o límite |
|---|---|---|
| P1-01 | La unidad solicitante puede cambiarse durante el borrador y queda fija después del primer envío. | El borrador V3 permite editar unidades con revalidación; la inmovilidad después del primer envío requiere verificación o adaptación. |
| P1-02 | El rol Solicitante es obligatorio para futuras operaciones de creación, edición y reenvío V2/V3, preservando el historial anterior. | P1a implementó cambios de rol y autorización en Reemplazos. No se infiere que todo el alcance futuro P1 esté terminado. |
| P1-03 | La búsqueda de funcionarios debe ser contextual, limitada y mostrar solo datos indispensables. | Existe búsqueda en el formulario; esta decisión específica requiere verificación o adaptación. |

Estas matrices son decisiones funcionales comunicadas y aprobadas por el responsable, no criterios de aceptación técnica ni autorización de implementación. P1a es la única subfase P1 aquí identificada como implementada; P1b, P1c, P2, P3 y P4 permanecen pendientes.

## Aspectos abiertos

Definir la autoridad y el contenido del memorándum V3; el procedimiento para confirmar a la persona finalmente contratada; el alcance técnico de versiones de solicitud, rectificaciones y devolución posterior a aprobación; la evidencia de tramitación externa que se registrará; y las reglas de importación SIRH. La generación de PDF y la formalización V3 permanecen pendientes. El [mapa V3](MAPA_FUNCIONAL_V3.md) conserva otros pendientes institucionales que requieren decisión antes de convertirse en restricciones del sistema.
