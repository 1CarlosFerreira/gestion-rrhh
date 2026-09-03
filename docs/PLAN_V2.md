# Plan de continuidad v2

## Alcance y evidencia

Auditoría breve al 2026-09-03, sin modificar código ni base de datos. Se revisaron migraciones, seeders, modelos, Actions/Services, Policies, controladores, rutas, vistas y pruebas de Personas, Dotación, permisos, Reemplazos y Documentos. La base local tiene las 38 migraciones aplicadas y 44 tablas (incluida `migrations`). No existe evidencia en el repositorio para determinar despliegues fuera de desarrollo; las tres migraciones del 2026-09-02 deben confirmarse en cada ambiente externo.

Los requisitos v2 confirmados son: organización jerárquica; dotación histórica; jefaturas titular/subrogante con vigencia; registro de permisos, feriados y licencias por funcionarios; aprobación jerárquica; validación de licencias por Gestión de Personas y constancia SIRH; necesidad por ausencia o vacante; coberturas múltiples no superpuestas; un trámite/PDF por reemplazante; e incorporación a dotación solo tras cargar el documento firmado. Horas Extraordinarias y DocDigital quedan diferidos.

## Componentes reutilizables

- Mantener autenticación, usuarios, Spatie Roles/Permissions y permisos granulares. Las decisiones deben depender de permisos y asignaciones vigentes, no del nombre del rol.
- Mantener `Persona`, normalización de RUT, estamentos y profesiones. Reconstruir sus vínculos laborales/organizacionales.
- Mantener el núcleo `tramites`, su secuencia, estados/transiciones configurables y `TransicionarTramite`; adaptar la unidad y el nuevo flujo de aprobaciones/cobertura.
- Mantener el historial inmutable y el patrón transaccional. Conviene generalizar eventos de ausencia/aprobación sin duplicar historiales aislados.
- Reutilizar storage privado, hash, versionado, descarga autorizada, plantillas, documentos generados y el generador PDF de Reemplazos; adaptar su asociación a una cobertura individual y al documento firmado.
- Reutilizar el enfoque de Policies y visibilidad por unidad, pero resolver alcance por descendencia jerárquica y responsabilidad vigente.
- Reutilizar de `CoberturaAusenciaService` el cálculo inclusivo, intervalos disponibles y detección de superposición; no su modelo de dominio actual.

## Decisiones confirmadas para v2

- La organización será un árbol configurable de Dirección, subdirecciones, departamentos, áreas, unidades y servicios. Cada nodo podrá depender de otro; la jerarquía oficial se cargará y validará posteriormente y no dependerá de nombres de roles hardcodeados.
- Se separan `Persona` (identidad única por RUT), `Usuario` (cuenta personal), dotación histórica, responsabilidad titular/subrogante, acceso operativo a unidades y roles/permisos generales de Spatie. Preparar solicitudes para una unidad no convierte a una secretaria o administrativo en jefatura ni le permite aprobar.
- Cada funcionario autorizado usará su propia cuenta, sin autorregistro ni cuentas compartidas, con acceso por RUT o correo institucional y contraseña. El usuario debe estar activo, podrá tener varios roles y conservará su cuenta aunque cambien su unidad o responsabilidad. Un `User` funcionario referencia a su `Persona`; el administrador técnico puede existir sin dotación activa.
- El alcance organizacional se resolverá después mediante dotación, responsabilidades y accesos vigentes. Una jefatura sigue siendo funcionaria y puede presentar solicitudes propias, pero nadie puede autoaprobarse.
- Los funcionarios podrán solicitar permisos y feriados. Las licencias médicas se informarán; Gestión de Personas las validará y registrará su ingreso a SIRH, sin aprobación de jefatura. Las aprobaciones seguirán la responsabilidad vigente: jefatura de la unidad, superior de la jefatura y Dirección para subdirectores. La subrogancia tendrá vigencia fechada.
- Ausencia, necesidad de cobertura y reemplazo son conceptos distintos. Una necesidad puede originarse por ausencia o vacante, cubrir parcialmente el período y dividirse entre varios reemplazantes sin superposición. Cada cobertura genera su propio trámite y documento.
- El reemplazante permanece como cobertura en trámite hasta registrar el documento firmado. Solo entonces se crea idempotentemente su dotación temporal, con trazabilidad; al finalizar, el vínculo permanece en el historial.
- El grado E.U.S. será un número informado manualmente por Gestión de Personas; `grados_eus` se retira. Se preservan la plantilla institucional de Reemplazo, sus assets y el generador Dompdf. La integración automática con DocDigital y Horas Extraordinarias quedan diferidas; el registro manual del documento firmado formará parte del flujo futuro.

## Inventario resumido

Riesgo: B=bajo, M=medio, A=alto. Conteo por clasificación: **MANTENER 19**, **ADAPTAR 10**, **REEMPLAZAR 7**, **ELIMINAR EN V2 1**, **DIFERIR 7** (total 44).

| Tabla | Propósito y uso actual | Concepto válido | Clase | Riesgo | Dependencias importantes |
|---|---|---:|---|---:|---|
| `migrations` | Control de migraciones de Laravel | Sí | MANTENER | A | Todo el esquema |
| `users` | Login, actores y auditoría; `User`, auth/admin | Sí | MANTENER | A | Roles, trámites, historiales, archivos |
| `password_reset_tokens` | Recuperación de contraseña | Sí | MANTENER | B | Auth |
| `sessions` | Sesiones de usuarios | Sí | MANTENER | B | Auth |
| `cache`, `cache_locks` | Caché y locks Laravel | Sí | MANTENER | B | Framework/Spatie |
| `jobs`, `job_batches`, `failed_jobs` | Colas Laravel | Sí | MANTENER | B | Framework; hoy sin filas |
| `permissions`, `roles` | Catálogo RBAC Spatie | Sí | MANTENER | A | `User`, middleware, Policies |
| `model_has_permissions`, `model_has_roles`, `role_has_permissions` | Asignaciones RBAC | Sí | MANTENER | A | Usuarios/permisos; permiso directo hoy vacío |
| `unidades_servicios` | Catálogo plano de 48 unidades; `UnidadServicio`, bandejas/dotación | Parcial | ADAPTAR | A | Muchas FK; agregar jerarquía o reemplazo compatible |
| `estamentos` | Catálogo de estamentos | Sí | MANTENER | M | Personas/reemplazos |
| `profesiones` | Profesiones ligadas opcionalmente a estamento | Sí | MANTENER | M | Vínculos/reemplazos; revisar catálogo ficticio/no validado |
| `tipos_tramite` | Tipos de proceso | Sí | ADAPTAR | M | Estados, trámites, plantillas |
| `estados_tramite` | Estados por tipo | Sí | ADAPTAR | A | Transiciones, trámites, historial |
| `transiciones_estado` | Motor configurable y permiso requerido | Sí | ADAPTAR | A | `TransicionarTramite`, guards, seeders |
| `tipos_reemplazo` | Mezcla motivos de ausencia y `Cargo Vacante` | No como unidad | REEMPLAZAR | A | Ausencias, reemplazos, requisitos |
| `clasificaciones_area` | Clasificación usada en revisión de reemplazo | Sí, por confirmar | ADAPTAR | M | Revisión/PDF |
| `grados_eus` | Catálogo vacío; FK ya sustituida por grado numérico informado | No actualmente | ELIMINAR EN V2 | M | FK vestigial en revisión; sin datos oficiales |
| `personas` | Identidad única por RUT; búsquedas/dotación/reemplazos | Sí | MANTENER | A | Vínculos, adjuntos, ausencias, coberturas |
| `persona_unidad_vinculos` | Dotación/asignación histórica con cargo y estado | Parcial | REEMPLAZAR | A | Personas, unidades, reemplazos; estados `varchar` ambiguos |
| `user_unidades` | Alcance de usuario por unidad y vigencia | Parcial | REEMPLAZAR | A | Policies, bandejas; duplica pertenencia organizacional y no expresa jefatura |
| `tramite_secuencias` | Correlativo anual | Sí | MANTENER | M | Generación de códigos con lock |
| `tramites` | Raíz de procesos y estado actual | Sí | ADAPTAR | A | FK directa a unidad plana; casi todo el dominio |
| `tramite_historial` | Eventos/transiciones inmutables | Sí | MANTENER | A | Trámites, usuarios, estados |
| `tipos_documento` | Catálogo documental | Sí | ADAPTAR | M | Adjuntos, plantillas, generados |
| `tramite_adjuntos` | Archivo privado, versión, hash y actor | Sí | ADAPTAR | A | Trámite/persona; documentos, SIRH y DocDigital |
| `tramite_reemplazos` | Un detalle 1:1 por trámite/cobertura | Parcial | REEMPLAZAR | A | Ausencia, personas, vínculo, catálogos, PDF |
| `reemplazo_revisiones_personal` | Revisión única de GP | Parcial | REEMPLAZAR | A | Trámite, clasificación, usuarios; no modela etapas jerárquicas |
| `requisitos_documentales` | Configuración de documentos obligatorios | Sí, pendiente | DIFERIR | M | Sin modelo ni filas; el guard la consulta directamente |
| `documento_plantillas` | Plantillas versionadas por proceso | Sí | ADAPTAR | M | Tipos y generados |
| `documentos_generados` | Salidas versionadas y snapshot metadata | Sí | ADAPTAR | A | Trámite, plantilla, adjunto, PDF |
| `ausencias_reemplazables` | Causa compartida de varias coberturas | Parcial | REEMPLAZAR | A | Mezcla ausencia/vacante; funcionario y fechas pueden ser nulos |
| `ausencia_reemplazable_historial` | Cierre/eventos de la causa | Parcial | REEMPLAZAR | M | Historial separado e incompleto; hoy 0 filas |
| `tramite_horas_extra` | Cabecera mensual HE | Sí fuera del alcance | DIFERIR | M | Trámites |
| `horas_extra_funcionarios` | Participantes/minutos HE | Sí fuera del alcance | DIFERIR | M | Personas, cabecera HE |
| `horas_extra_planillas_sirh` | Versiones de planilla PDF HE | Sí fuera del alcance | DIFERIR | M | Adjuntos, usuarios |
| `horas_extra_revisiones` | Revisión inmutable de planilla HE | Sí fuera del alcance | DIFERIR | M | Planillas, usuarios |
| `horas_extra_informes_tecnicos` | Datos de informe HE | Sí fuera del alcance | DIFERIR | M | Cabecera HE, usuarios |
| `docdigital_registros` | Envío/formalización externa | Sí fuera del alcance | DIFERIR | M | Trámite, documentos, adjuntos |

No hay tablas duplicadas literalmente. Están sin datos locales: `requisitos_documentales`, `grados_eus`, todas las tablas HE, `docdigital_registros`, `ausencia_reemplazable_historial`, colas y permisos directos; salvo grados y requisitos, sí tienen código real y/o pruebas. `requisitos_documentales` carece de modelo pero se consulta desde `ReemplazoTransitionGuard`. Los seeders declaran explícitamente usuarios, personas, RUT, cargos, dotación y trámites ficticios; los catálogos también contienen valores no validados institucionalmente. No debe asumirse que las 11 ausencias, 13 trámites, 12 adjuntos o 5 documentos locales sean productivos sin validación de origen.

## Elementos a reconstruir y deuda técnica

- Organización plana: no existe `parent_id`, cierre histórico de jerarquía ni consulta de ancestros/descendientes.
- `user_unidades` confunde visibilidad con responsabilidad; su `unique(user_id, unidad)` impide varios períodos históricos para una misma asignación.
- `persona_unidad_vinculos` llama “dotación” a un vínculo genérico; `status`, `cargo_texto`, fechas nulas y ausencia de exclusión temporal permiten estados contradictorios y superposición.
- Ausencia y vacante están acopladas a `tipo_reemplazo`; una vacante no tiene funcionario y una ausencia debe ser registrada por el funcionario y validada según tipo.
- No hay responsables titulares/subrogantes, aprobaciones jerárquicas, registro SIRH de licencias ni regla transaccional que incorpore al reemplazante después del firmado.
- La no superposición se valida en aplicación al enviar/completar; no existe garantía de base de datos y los borradores superpuestos son permitidos deliberadamente.
- Hay conceptos duplicados entre causa y cobertura (`tipo_reemplazo`, funcionario, justificación y fechas). Las FK restrictivas desde trámites/reemplazos/archivos elevan el riesgo de migración.
- `DestinatarioSolicitudReemplazo` depende directamente del rol `Subdirector/a de Gestión y Desarrollo de las Personas`; debe usar responsabilidad vigente. Los roles base aparecen en seeders y tests, pero la autorización productiva usa mayormente permisos, lo cual sí es reusable.
- Existen reglas/estados de flujo hardcodeados en Actions, consultas y vistas (`BORRADOR`, `EN_REVISION`, `FORMALIZADA`, grupos de bandeja y etapas visuales). El motor es configurable solo parcialmente.
- El historial es inmutable vía modelo, no mediante controles de base; escrituras directas podrían alterarlo. Algunas pruebas actualizan `estado_tramite_id` directamente para preparar escenarios, aunque el código productivo centraliza transiciones.

## Modelo mínimo v2 (sin columnas)

| Entidad | Responsabilidad y relaciones principales | Reutilización / tabla nueva |
|---|---|---|
| Unidad organizacional | Árbol institucional; padre/hijos, dotación, responsables y trámites | Adaptar `unidades_servicios` o migrarla de forma compatible; no duplicar catálogos |
| Responsable de unidad | Titular/subrogante vigente; persona/usuario + unidad + período + tipo | Tabla nueva; sustituye la semántica de `user_unidades` |
| Vínculo de dotación | Historial laboral de persona en unidad/cargo; origen documental y vigencia | Nueva tabla o reconstrucción de `persona_unidad_vinculos` |
| Ausencia | Hecho informado por funcionario (permiso, feriado, licencia), período y estado | Tabla nueva; no reutilizar `ausencias_reemplazables` sin separar conceptos |
| Aprobación/validación | Decisiones ordenadas, actor/responsabilidad, resultado, observación y fecha; liga ausencia/trámite | Tabla nueva; reemplaza revisión única |
| Registro de licencia SIRH | Validación GP y constancia de registro externo/documento soporte | Tabla nueva ligada a ausencia/aprobación/adjunto |
| Necesidad de cobertura | Demanda de una unidad causada por ausencia o cargo vacante; período y cantidad/estado | Tabla nueva; evolución conceptual de `ausencias_reemplazables` |
| Cobertura individual | Un reemplazante y período no superpuesto por necesidad; origina exactamente un trámite/PDF | Tabla nueva/reconstrucción de `tramite_reemplazos`; reutiliza `tramites` y documentos |
| Documento firmado | Versión final autorizada de la cobertura, fecha/actor y adjunto | Adaptar `tramite_adjuntos`/`documentos_generados`; puede requerir tabla de formalización nueva |
| Alta de dotación | Evento idempotente posterior al documento firmado que crea el vínculo y conserva trazabilidad | Tabla/evento nuevo ligado a cobertura, documento y vínculo de dotación |

## Estrategia y fases

**Alternativa A — solo desarrollo (recomendada por la evidencia actual).** Crear un punto de respaldo, conservar código reusable, consolidar migraciones base de infraestructura/catálogos/núcleo documental y reconstruir en una línea v2 las tablas organizacionales, dotación, ausencias, aprobaciones, necesidades y coberturas. Recrear la base y sembrar solo catálogos validados y fixtures ficticios mínimos. Esta opción requiere confirmar formalmente que no hay datos reales: el repositorio no contiene información de despliegue y la base local coincide con un entorno de demostración.

**Alternativa B — conservar datos.** No consolidar migraciones ya ejecutadas. Agregar estructuras v2 en paralelo, mapas de equivalencia e identificadores de origen; migrar jerarquía/unidades, vínculos, causas y coberturas en lotes idempotentes; preservar `tramites`, historial, rutas privadas, hashes y versiones; reconciliar registros ambiguos; hacer doble lectura controlada, validar conteos/hashes y cortar por fase. Retirar estructuras v1 solo en una versión posterior y después de respaldo/restauración probada.

Fases recomendadas:

1. Confirmar ambientes/datos reales, jerarquía oficial, responsables y reglas de aprobación; crear rama/tag de respaldo.
2. Organización jerárquica, responsables y autorización por alcance.
3. Dotación histórica y asociación persona↔usuario.
4. Ausencias, validación de licencias y registro SIRH.
5. Necesidades y coberturas individuales con exclusión de superposición.
6. Flujo de trámite, PDF individual, firmado y alta idempotente en dotación.
7. Migración/conciliación y pruebas; diferir HE y DocDigital.

## Decisiones pendientes

- Confirmar si algún ambiente contiene datos reales y cuáles migraciones del 2026-09-02 están desplegadas fuera de desarrollo.
- Árbol oficial de unidades, vigencias históricas y tratamiento de unidades renombradas/fusionadas.
- Identidad entre `users` y `personas`, y quiénes tendrán cuenta para registrar ausencias.
- Tipos y reglas de ausencia, cadena de aprobación, facultades del subrogante y tratamiento de conflictos de vigencia.
- Evidencia/campos requeridos para validación y registro SIRH; reglas oficiales de vacantes, cobertura parcial y documento firmado.
- Catálogos oficiales (profesiones, clasificación, grados) y plantilla definitiva del PDF.

Recomendación: **refactorización selectiva**, no reinicio completo. Antes de comenzar, crear desde `main` en `203426a` una rama de respaldo, por ejemplo `backup/pre-v2-2026-09-03` (y opcionalmente un tag anotado), sin hacerlo durante esta auditoría.
