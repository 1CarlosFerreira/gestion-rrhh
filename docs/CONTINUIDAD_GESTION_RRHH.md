# Continuidad de Gestión RRHH

**Fecha de referencia:** 18-11-2026, America/Santiago. **Estado:** propuesta pendiente de aprobación humana. El proyecto continúa activo y se pausa temporalmente para evaluar otra necesidad institucional.

## Base y evidencia

| Elemento | Estado conservado | Límite de verificación |
|---|---|---|
| Git | Rama `feature/v2-base-limpia`; HEAD `ed06c12a055968f2580b5bc909f10e8d86f38ce3`; únicamente los dos documentos de esta entrega aparecen como archivos nuevos sin seguimiento. | Lectura local de `git status`, rama, log y remoto. El estado final se informa en conversación. |
| Publicación | GitHub confirmó previamente el `push` hasta `ed06c12`, según la salida aportada por el responsable humano. | La salida no se reprodujo aquí ni se consultó GitHub en esta entrega; se conserva la evidencia comunicada por el responsable con ese límite. |
| P1a | Implementada en el commit `f28ad08`, sobre rol Solicitante y autorizaciones de Reemplazos. | Se inspeccionaron commit y archivos afectados; no se ejecutó la aplicación en esta entrega. |
| Calidad | Última ejecución SQLite reportada: 406 pruebas aprobadas y 2699 aserciones; Pint reportado aprobado. | Son resultados comunicados por el responsable, sin salida de ejecución disponible aquí. No se repitieron pruebas ni Pint. |

La autorización vigente permite modificar exclusivamente este documento y [Decisiones funcionales](DECISIONES_FUNCIONALES_RRHH.md), verificar contenido y Git. No permite modificar la aplicación, ejecutar pruebas, migraciones o seeders, instalar dependencias, crear commits ni hacer push. Los documentos siguen pendientes de aceptación humana.

## Estado funcional

El [README maestro](../README.md) describe el monolito modular Laravel. Reemplazos V2 histórico dispone de borrador, revisión, PDF, formalización administrativa y alta en dotación. Las solicitudes nuevas V3 transitorias tienen contexto organizacional separado, respaldos, períodos, aprobación con compromiso y reserva opcional, y anulación previa al documento con liberación. La generación de PDF y la formalización V3 permanecen pendientes. DocDigital y SIRH son externos y no están integrados. Las matrices P0-01 a P0-07 y P1-01 a P1-03 fueron comunicadas y aprobadas expresamente por el responsable humano y se registran en [Decisiones funcionales](DECISIONES_FUNCIONALES_RRHH.md); su aprobación funcional no acredita implementación.

## Componentes reutilizables

Identidad Persona/User y RUT normalizado; roles y permisos; estructura, responsabilidades y accesos por unidad; dotación histórica; raíz `tramites`, secuencias, transiciones centralizadas e historial inmutable; expediente privado con hash y versiones; plantillas, PDF y snapshots; servicios de períodos y concurrencia de respaldos transitorios. Su reutilización en otro sistema o proceso exige comprobar permisos, alcance y semántica propios, sin copiar reglas de Reemplazos automáticamente.

## Pendientes, dependencias y riesgos

- **P1b y P1c:** pendientes según el responsable. Sus objetivos y criterios exactos no constan en un plan disponible aquí; no se les asigna un alcance supuesto.
- **P2, P3 y P4:** pendientes. La correspondencia P0/P1 ya está registrada; antes de ordenar dependencias de ejecución se requiere el alcance y plan aprobado de esas fases.
- **V3:** memorándum/documento, selección o confirmación administrativa definitiva del candidato, formalización, versiones y correcciones posteriores, además de seguimiento externo, requieren decisiones y fases propias. La aprobación sin candidato no implica contratación de una persona no identificada.
- **Riesgos:** confundir V2 con V3; tratar una regla funcional aprobada como código disponible; perder trazabilidad de datos originales, reservas o versiones; aplicar acceso a las tres unidades V3 como si ya cumpliera P0-01; equiparar formalización manual a firma o estado de DocDigital; inferir datos SIRH o efectos contractuales desde ausencia de registros.

## Próxima acción recomendada

El responsable humano revisa y acepta o corrige estos dos documentos. Para retomar Gestión RRHH, definir el alcance y criterios de P1b–P4, resolver las brechas entre las matrices aprobadas y la implementación, confirmar las decisiones institucionales bloqueantes y autorizar por separado la fase siguiente. Reutilizar el núcleo existente y definir pruebas de regresión antes de implementar. No iniciar funcionalidades a partir de esta ficha.
