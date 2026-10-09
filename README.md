# Sistema de Gestión de Trámites RRHH

Aplicación interna del Hospital de Illapel para administrar procesos y documentos de Recursos Humanos sobre un núcleo transversal de trámites. Este README es el documento maestro del estado vigente del proyecto: describe lo que existe en el código actual, cómo ejecutarlo y qué capacidades siguen fuera de alcance.

## Índice

1. [Descripción general](#1-descripción-general)
2. [Stack tecnológico](#2-stack-tecnológico)
3. [Arquitectura general](#3-arquitectura-general)
4. [Estructura organizacional](#4-estructura-organizacional)
5. [Usuarios, roles y permisos](#5-usuarios-roles-y-permisos)
6. [Accesos operativos](#6-accesos-operativos)
7. [Personas y dotación](#7-personas-y-dotación)
8. [Núcleo de trámites](#8-núcleo-de-trámites)
9. [Expediente y archivos](#9-expediente-y-archivos)
10. [Módulo de Reemplazos](#10-módulo-de-reemplazos)
11. [Pantallas principales](#11-pantallas-principales)
12. [Estado actual del proyecto](#12-estado-actual-del-proyecto)
13. [Instalación desde cero](#13-instalación-desde-cero)
14. [Operación diaria](#14-operación-diaria)
15. [Base de datos](#15-base-de-datos)
16. [Pruebas y calidad](#16-pruebas-y-calidad)
17. [Storage y documentos](#17-storage-y-documentos)
18. [Solución de problemas frecuentes](#18-solución-de-problemas-frecuentes)
19. [Estructura relevante del repositorio](#19-estructura-relevante-del-repositorio)
20. [Convenciones para continuar desarrollando](#20-convenciones-para-continuar-desarrollando)

## 1. Descripción general

El sistema centraliza la tramitación administrativa de RRHH, mantiene trazabilidad de cada cambio y reutiliza capacidades comunes —autorización, estados, historial, expediente y generación documental— entre distintos procesos.

La arquitectura es un monolito modular Laravel. La tabla `tramites` es la raíz transversal y cada proceso agrega su propio detalle. Actualmente, **Solicitud de Reemplazo es el único proceso funcional desarrollado de extremo a extremo**: permite preparar un borrador, enviarlo a Gestión de Personas, revisarlo, generar su PDF, formalizarlo y crear la incorporación temporal del reemplazante en la dotación.

Ausencias, permisos administrativos, feriados, licencias médicas, Horas Extraordinarias, SIRH y DocDigital no son módulos funcionales actuales. Sus menciones en documentos institucionales representan contexto o necesidades futuras, no comportamiento disponible en la aplicación.

## 2. Stack tecnológico

Las versiones se obtienen de las imágenes Docker y los archivos de bloqueo vigentes:

| Componente | Versión o implementación actual |
|---|---|
| PHP | 8.3 FPM sobre Debian Bookworm |
| Laravel | 12.67.0 |
| MySQL | 8.4, `utf8mb4_unicode_ci` |
| Nginx | 1.28 Alpine |
| Composer | 2, incluido en la imagen de aplicación |
| Node.js | 22 Alpine |
| Blade | Motor de vistas del servidor |
| Tailwind CSS | 3.4.19 |
| Alpine.js | 3.16.3 |
| Vite | 7.3.6 |
| Laravel Vite Plugin | 2.1.0 |
| Spatie Laravel Permission | 8.3.0 |
| Dompdf para Laravel | 3.1.2 |
| PhpSpreadsheet | 2.4.7; instalada, sin flujo funcional activo en el código actual |
| PHPUnit | 11.5.56 |
| Laravel Pint | 1.24.1 |

Todo el entorno del proyecto se ejecuta con Docker Compose. PHP, Composer, Node, npm, MySQL y Nginx no deben instalarse directamente en Windows para trabajar con este repositorio. La aplicación y PHP usan la zona horaria `America/Santiago`.

## 3. Arquitectura general

Los dominios principales son:

- **Usuarios y seguridad:** autenticación por correo o RUT, cuentas activas, roles y permisos con Spatie.
- **Organización institucional:** árbol configurable de unidades y tipos de unidad.
- **Responsabilidades:** titularidades y subrogancias institucionales con vigencia.
- **Accesos operativos:** unidades sobre las que una cuenta puede operar, directamente o incluyendo descendientes.
- **Personas:** identidad institucional única por RUT.
- **Dotación:** vínculos laborales históricos entre personas y unidades.
- **Trámites:** raíz común, numeración, estado actual, transiciones e historial.
- **Expediente:** adjuntos privados, versiones, hash y descarga autorizada.
- **Reemplazos:** detalle funcional del proceso actualmente implementado.
- **Documentos generados:** plantilla lógica, PDF, snapshot y relación con el expediente.

Estas dimensiones son independientes:

```text
RBAC (rol/permisos)
        +
alcance funcional u operativo sobre unidades
        =
capacidad efectiva para una acción

dotación                      responsabilidad institucional
(dónde trabaja una persona)   (titular o subrogante de una unidad)
        ≠                                  ≠
acceso operativo              rol/permisos de la cuenta
```

Asignar un rol no concede por sí solo alcance sobre una unidad. Tener dotación tampoco crea automáticamente una cuenta, un rol ni un acceso operativo. Ser titular o subrogante puede aportar alcance funcional a Solicitudes de Reemplazo cuando la responsabilidad está vigente y permite aprobar, pero continúa siendo un concepto separado.

La lógica de negocio relevante vive en `app/Actions` y `app/Services`; la validación HTTP en Form Requests; la autorización en Policies y Gates; y los controladores coordinan esos componentes.

## 4. Estructura organizacional

`unidades_organizacionales` representa la institución mediante una lista de adyacencia:

- `parent_id` apunta a la unidad superior y es `null` para una raíz.
- Cada unidad tiene código estable, nombre, sigla opcional, tipo, orden y estado activo.
- `participa_en_aprobacion` identifica nodos utilizables por la resolución de responsables superiores; el flujo de aprobaciones jerárquicas todavía no está implementado.
- Los tipos configurables están en `tipos_unidad_organizacional`. El seeder incluye Dirección, Subdirección, Departamento, Área, Unidad, Servicio, Oficina y Otro.
- El servicio de estructura obtiene ancestros, descendientes, nivel y ruta legible.
- Un movimiento no puede convertir a la propia unidad o a uno de sus descendientes en su padre.
- La navegación por alcance descendiente considera solamente unidades activas.
- Las unidades usadas se desactivan en vez de eliminarse. El modelo impide eliminar una unidad que todavía tenga hijos.

`EstructuraOrganizacionalOficialSeeder` carga idempotentemente el árbol institucional conocido. `parent_id` determina la jerarquía; el tipo de unidad es descriptivo y no impone combinaciones rígidas padre-hijo.

### Responsabilidades institucionales

`unidad_responsables` registra una Persona como `TITULAR` o `SUBROGANTE` de una unidad durante un periodo inclusivo:

- admite fecha de término abierta;
- conserva creador, último editor y observación;
- rechaza solapamientos del mismo tipo en una unidad;
- una persona no puede mantener dos titularidades institucionales superpuestas;
- la titularidad exige dotación en la misma unidad durante todo su periodo;
- una responsabilidad iniciada conserva persona, unidad, tipo y fecha inicial;
- no se elimina físicamente: se cierra su vigencia;
- el responsable operativo vigente prioriza al subrogante sobre el titular;
- para actuar se requiere una cuenta asociada activa y, cuando corresponda, `puede_aprobar`.

El código puede resolver el primer responsable habilitado en unidades superiores y excluir a una persona para evitar autoasignaciones futuras. Esa infraestructura existe, pero todavía no hay un proceso de aprobación jerárquica que la consuma.

## 5. Usuarios, roles y permisos

Una `Persona` representa identidad; un `User` representa una cuenta de acceso. La asociación es opcional y uno a uno. Un administrador puede crear una cuenta para una persona, administrar su correo, restablecer su contraseña, activar o desactivar la cuenta y asignarle varios roles. No existe autorregistro público.

El login acepta correo o RUT y contraseña. El RUT se normaliza antes de autenticar, las cuentas inactivas no pueden ingresar y existe limitación de cinco intentos por clave de identificador/IP. Los usuarios pueden actualizar su perfil y contraseña.

### Roles sembrados

| Rol | Capacidades sembradas |
|---|---|
| `Administrador` | Recibe todos los permisos declarados, incluida administración, visibilidad global de trámites y dotación global. Su dashboard muestra resúmenes administrativos. |
| `Gestión de Personas` | Ve personas, dotación y responsabilidades; revisa Reemplazos, genera documentos, formaliza, administra adjuntos y posee `tramites.ver_todos` y `dotacion.ver_todas`. |
| `Solicitante` | Puede crear Reemplazos y adjuntos, ver trámites de sus unidades autorizadas, personas, estructura, responsabilidades y dotación dentro de su alcance. |
| `Jefatura` | Puede crear Reemplazos, cargar y descargar adjuntos, consultar estructura y dotación, y ver trámites de sus unidades autorizadas. El rol no la convierte por sí solo en responsable institucional. |
| `Funcionario` | No recibe permisos funcionales iniciales. Es una base para futuras capacidades personales. |

Los roles son administrables y un usuario puede tener más de uno. La autorización productiva debe depender de permisos, estado de cuenta y alcance; no del nombre del rol, salvo la selección visual del dashboard administrativo actual.

### Permisos relevantes

| Grupo | Permisos actuales |
|---|---|
| Administración | `admin.usuarios`, `admin.roles_permisos` |
| Trámites | `tramites.ver_unidades`, `tramites.ver_todos`, `tramites.crear` |
| Adjuntos | `tramites.adjuntos.cargar`, `tramites.adjuntos.descargar`, `tramites.adjuntos.anular` |
| Reemplazos | `reemplazos.crear`, `reemplazos.revisar`, `reemplazos.generar_documento`, `reemplazos.formalizar` |
| Estructura | `estructura_organizacional.ver`, `estructura_organizacional.gestionar`, `tipos_unidad_organizacional.gestionar` |
| Responsabilidades | `responsabilidades.ver`, `responsabilidades.gestionar` |
| Accesos operativos | `accesos_operativos.ver`, `accesos_operativos.gestionar` |
| Dotación | `dotacion.ver`, `dotacion.ver_todas`, `dotacion.gestionar` |
| Calidades contractuales | `calidades_contractuales.ver`, `calidades_contractuales.gestionar` |
| Personas | `personas.ver`, `personas.gestionar` |

`tramites.ver_todos` omite el filtro por unidad para ver trámites y, combinado con el permiso funcional correspondiente, también permite revisar, generar o formalizar Reemplazos sin un acceso operativo específico. Actualmente lo reciben Administrador y Gestión de Personas. `dotacion.ver_todas` cumple el propósito global equivalente para dotación.

## 6. Accesos operativos

`user_unidad_accesos` concede alcance operativo a una cuenta activa asociada a una Persona. Cada registro conserva unidad, periodo inclusivo, alcance, observación y auditoría.

Los alcances admitidos son:

- `SOLO_UNIDAD`: incluye únicamente la unidad asignada.
- `UNIDAD_Y_DESCENDIENTES`: incluye la unidad y todos sus descendientes activos según el árbol actual.

Los accesos equivalentes no pueden superponerse. Una asignación ya iniciada conserva usuario, unidad, alcance y fecha inicial; se finaliza estableciendo `vigente_hasta` y no se reabre desde la edición general.

El alcance descendiente se resuelve dinámicamente: mover una unidad dentro del árbol cambia qué descendientes cubre un acceso, sin reescribir su registro histórico.

Un acceso operativo no concede permisos. Para actuar normalmente deben coincidir:

1. cuenta activa;
2. permiso Spatie para la operación;
3. unidad incluida en el alcance vigente.

Para crear y editar Solicitudes de Reemplazo, el alcance funcional es la unión de los accesos operativos y las responsabilidades titular/subrogante vigentes con `puede_aprobar`. Este alcance no equivale a dotación ni crea una jefatura.

## 7. Personas y dotación

### Personas

`personas` mantiene una identidad única por RUT normalizado, nombres, apellidos y estado activo. Las búsquedas admiten RUT o componentes del nombre. Una Persona puede existir sin cuenta y puede aparecer en dotación, responsabilidades o Reemplazos.

La cuenta asociada, si existe, conserva además correo único, contraseña, roles, estado activo y último acceso. El RUT también se normaliza al guardarlo en `users`.

### Dotación histórica

`persona_unidad_vinculos` registra hechos laborales con:

- persona y unidad;
- estamento;
- profesión opcional y compatible con el estamento;
- calidad contractual;
- cargo o función y su valor normalizado;
- grado E.U.S. numérico opcional;
- vigencia inclusiva desde/hasta;
- origen `MANUAL`, `IMPORTACION` o `DOCUMENTO_FIRMADO`;
- trámite de origen cuando corresponde;
- actor creador y último editor.

El estado `FUTURO`, `VIGENTE` o `FINALIZADO` se calcula desde las fechas; no es un campo mutable. Los vínculos no se eliminan físicamente.

Se admiten vínculos simultáneos cuando representan hechos laborales diferentes. Se rechaza la superposición de un vínculo equivalente, definido por persona, unidad, calidad contractual y cargo normalizado. Una vez iniciado, sus datos laborales se preservan: para cambiar identidad laboral debe cerrarse y crearse uno nuevo. Tampoco se puede acortar un vínculo si deja una titularidad institucional fuera de la cobertura laboral.

### Formas de alta

- **Manual:** la Policy actual reserva la gestión a una cuenta activa con rol `Administrador` y permiso `dotacion.gestionar`. La misma operación puede crear coordinadamente una responsabilidad titular o subrogante.
- **Desde Reemplazo formalizado:** el flujo interno crea un vínculo con origen `DOCUMENTO_FIRMADO`, usando el reemplazante, la unidad, los antecedentes laborales formalizados y el periodo efectivo de cobertura. Este origen está reservado al proceso y no puede seleccionarse manualmente.

Los vínculos nacidos de una formalización no se editan desde la pantalla general de Dotación. El campo `origen_tramite_id` es único y hace idempotente el alta por trámite.

## 8. Núcleo de trámites

`tramites` es la raíz física de los procesos. Cada registro contiene:

- `public_id`: ULID usado también como clave pública de ruta;
- `codigo`: correlativo anual con formato generado por `tramite_secuencias`;
- tipo de trámite;
- estado actual;
- unidad organizacional;
- usuario creador;
- fechas de envío y finalización.

Los tipos están en `tipos_tramite`; los estados en `estados_tramite`; y las acciones permitidas en `transiciones_estado`. Una transición configura origen, destino, código de acción, permiso requerido, obligatoriedad de observación y estado activo.

`TransicionarTramite` es la única vía productiva para cambiar el estado de un trámite existente. La operación:

1. abre una transacción y bloquea el trámite;
2. encuentra una transición activa válida para su tipo y estado;
3. valida el permiso configurado;
4. exige observación cuando corresponde;
5. ejecuta los guards de dominio registrados;
6. cambia el estado y las marcas temporales aplicables;
7. agrega un evento a `tramite_historial`.

El historial almacena actor, acción, estados de origen/destino, observación, metadata y fecha. El modelo prohíbe actualizar o eliminar eventos desde la aplicación.

La visibilidad combina cuenta activa, permisos y alcance. `tramites.ver_todos` da visibilidad global. Sin ese permiso, los Reemplazos se filtran por las unidades que el usuario puede operar según acceso o responsabilidad vigente y por los permisos funcionales que posea.

## 9. Expediente y archivos

Los adjuntos viven en `storage/app/private`, nunca en `public/storage`. Se entregan exclusivamente a través de controladores que comprueban permiso, visibilidad del trámite y pertenencia del archivo.

Cada `tramite_adjuntos` registra:

- nombre original saneado;
- nombre físico ULID;
- ruta privada única;
- MIME real;
- tamaño en bytes;
- SHA-256;
- usuario y fecha de carga;
- tipo documental y persona opcionales;
- versión y adjunto reemplazado;
- estado `ACTIVO`, `REEMPLAZADO` o `ANULADO`.

Una corrección crea una nueva versión y marca la anterior como reemplazada. La anulación es lógica; el modelo impide eliminar físicamente el registro desde la aplicación. Carga, versionado y anulación generan eventos en el historial del trámite.

Los adjuntos generales aceptan PDF, DOC, DOCX, XLS, XLSX, CSV, JPG y PNG hasta 10 MiB, con comprobación adicional del MIME real. Los PDF pueden visualizarse en línea; los demás formatos se descargan. El contenido nunca se ejecuta ni interpreta. Actualmente no existe análisis antivirus.

## 10. Módulo de Reemplazos

### Contexto común de solicitud de contrato (Fase 2 V3)

Las solicitudes nuevas del formulario V3 crean `solicitudes_contrato` con modalidad `TRANSITORIA`, unidad solicitante, unidad origen y unidad destino elegidas por separado. `PERMANENTE` está representada en el enum y la tabla, sin ruta ni workflow. `tramites.created_by` sigue siendo el registrador real. La autoridad de la solicitud se obtiene de responsabilidades vigentes de la unidad solicitante, no del registrador ni de campos enviados por el formulario. Cada una de las tres unidades requiere acceso operativo vigente y el permiso `reemplazos.crear`. `tramites.ver_todos` solo da visibilidad; las acciones de revisión, generación y formalización en V2/V3 requieren alcance operativo o el permiso funcional `reemplazos.alcance_global`.

Para el acto de registro se admiten `TITULAR` y `SUBROGANTE`. Si las responsabilidades vigentes identifican a una sola Persona, esa Persona se guarda como autoridad, aunque no tenga cuenta. Cuando hay una única responsabilidad, se conserva su ID; si la misma Persona figura en ambas, el ID de responsabilidad queda nulo y el historial conserva ambos IDs sin escoger prioridad. Si no hay autoridad vigente o las responsabilidades apuntan a personas distintas, se informa un error controlado. La precedencia institucional entre titular y subrogante para este acto continúa por confirmar.

La validación reutilizada de V2 para `funcionario_id` exige una Persona activa con vínculo de dotación vigente **en la unidad origen, en la fecha inicial del periodo origen**. Esto comprueba el vínculo que V2 conoce; no afirma que unidad solicitante y destino sean iguales al origen ni establece una semántica institucional adicional de dotación. El PDF V2 identifica al creador como remitente y usa una sola unidad. Por esa razón, la generación de ese PDF se bloquea para solicitudes con contexto V3 hasta definir la autoridad documental aplicable; las solicitudes V2 anteriores siguen usando su flujo documental vigente.

Solo los trámites V2 históricos, identificados por la ausencia de fila `solicitudes_contrato`, conservan la edición y el envío con `unidad_organizacional_id`. Toda creación nueva exige las tres unidades V3; los parámetros legacy no pueden crear un trámite nuevo ni convertir una solicitud V3 en V2. No se deducen retrospectivamente solicitante, autoridad, origen ni destino ni se ejecuta un backfill. La revisión, documento y formalización V3 completa, así como las reglas de respaldo y contratación permanente, quedan para fases posteriores.

La Fase 3A V3 agrega únicamente las tablas y modelos de respaldos transitorios versionados, afectaciones y reservas históricas. No activa aún compromiso, reserva o liberación automática en los flujos de Reemplazos. El diseño y sus límites se describen en [`docs/FASE_3A_RESPALDOS_TRANSITORIOS.md`](docs/FASE_3A_RESPALDOS_TRANSITORIOS.md).

La Fase 3B V3 incorpora servicios internos de validación de períodos inclusivos y escrituras estructurales protegidas con transacciones y bloqueos por persona. Todavía no los conecta a los flujos administrativos. Su contrato de concurrencia y pruebas se documentan en [`docs/FASE_3B_PERIODOS_CONCURRENCIA.md`](docs/FASE_3B_PERIODOS_CONCURRENCIA.md).

Cada Solicitud de Reemplazo tiene un único detalle `tramite_reemplazos` y, como máximo, un reemplazante. La unidad del trámite raíz conserva el contexto principal V2 y representa la unidad solicitante cuando existe `solicitudes_contrato`.

Los tipos sembrados actualmente son `LICENCIA_MEDICA`, `PERMISO`, `LICENCIA_MATERNAL` y `CARGO_VACANTE`. Son motivos del Reemplazo; no constituyen un módulo independiente de ausencias o licencias.

### Creación y borrador

Crear requiere `reemplazos.crear`, cuenta activa y acceso operativo a las tres unidades explícitas. La edición y el envío de trámites V2 históricos conservan el alcance funcional sobre su unidad. El borrador se crea transaccionalmente con ULID, código correlativo, estado `BORRADOR`, detalle 1:1 e historial inicial.

El borrador es progresivo. Puede dejar pendientes funcionario, tipo, reemplazante, antecedentes laborales propuestos, periodos y justificación, siempre que los datos parciales informados sean internamente consistentes. Una vez persistido admite adjuntos.

El funcionario se elige entre Personas activas con dotación vigente en la unidad V2 o en la unidad origen V3 en la fecha inicial. El reemplazante puede ser una Persona existente o registrarse por RUT y nombre desde el formulario; esto no le crea cuenta, acceso ni vínculo de dotación.

El borrador puede editarse en `BORRADOR` y `DEVUELTA_PARA_CORRECCION`. Cambiar una unidad vuelve a validar su alcance; cambiar la unidad origen vuelve a validar la dotación del funcionario.

### Periodos y cobertura

La solicitud distingue cuatro fechas:

- **Periodo total del funcionario ausente:** `fecha_funcionario_desde` a `fecha_funcionario_hasta`.
- **Periodo efectivo del reemplazante:** `fecha_reemplazante_desde` a `fecha_reemplazante_hasta`.

Ambos periodos son inclusivos. El periodo efectivo debe quedar completamente contenido en el periodo total. Puede cubrir todos los días o solo una parte; el modelo calcula días totales, cubiertos y sin cobertura. Los días restantes no se asignan automáticamente a otra persona.

### Validaciones reales

Al guardar un borrador se comprueba, según los campos presentes:

- unidad activa y autorizada;
- funcionario activo y con dotación en la unidad al inicio;
- tipo de reemplazo activo;
- pares de fechas completos y ordenados;
- periodo del reemplazante contenido en el del funcionario;
- funcionario y reemplazante distintos;
- profesión compatible con el estamento;
- ausencia de otro Reemplazo activo superpuesto para el mismo funcionario.

Para enviar o reenviar también son obligatorios:

- unidad y funcionario;
- tipo de reemplazo;
- reemplazante;
- estamento y calidad contractual propuestos del reemplazante;
- cargo o función del reemplazante;
- los dos periodos completos;
- justificación.

La profesión del reemplazante sigue siendo opcional. Los estados que bloquean superposición son `BORRADOR`, `ENVIADA_GESTION_PERSONAS`, `EN_REVISION`, `DEVUELTA_PARA_CORRECCION` y `LISTA_GENERAR_DOCUMENTO`.

El guard contiene compatibilidad futura con una tabla `requisitos_documentales`, pero ninguna migración actual crea esa tabla. Por tanto, hoy no hay una matriz efectiva de documentos obligatorios por tipo de Reemplazo.

### Flujo vigente

```text
BORRADOR
   │ ENVIAR_A_GESTION_PERSONAS
   ▼
ENVIADA_GESTION_PERSONAS
   │ INICIAR_REVISION
   ▼
EN_REVISION
   ├── DEVOLVER_PARA_CORRECCION ──► DEVUELTA_PARA_CORRECCION
   │                                      │
   │                         REENVIAR_A_GESTION_PERSONAS
   │                                      │
   │                                      └──► ENVIADA_GESTION_PERSONAS
   │
   └── APROBAR_ANTECEDENTES ─────► LISTA_GENERAR_DOCUMENTO
                                             │ GENERAR_DOCUMENTO
                                             ▼
                                     DOCUMENTO_GENERADO
                                             │ FORMALIZAR_REEMPLAZO
                                             ▼
                                         FORMALIZADA
```

| Acción | Permiso requerido | Regla principal |
|---|---|---|
| Enviar o reenviar | `reemplazos.crear` | Datos completos, alcance sobre la unidad y validaciones de envío. |
| Iniciar revisión | `reemplazos.revisar` | Solicitud enviada y alcance operativo, salvo visibilidad global. |
| Devolver | `reemplazos.revisar` | Requiere una observación. Conserva datos y adjuntos. |
| Aprobar antecedentes | `reemplazos.revisar` | Exige revisión administrativa completa. |
| Generar documento | `reemplazos.generar_documento` | Solo desde `LISTA_GENERAR_DOCUMENTO`. |
| Formalizar | `reemplazos.formalizar` | Solo desde `DOCUMENTO_GENERADO`, con documento generado vigente. |

`tramites.ver_todos` reemplaza el requisito de acceso específico para revisión, generación y formalización. De lo contrario se necesita acceso operativo vigente a la unidad.

### Gestión de Personas

La bandeja presenta trámites activos y finalizados, con búsqueda por código o personas y filtros de estado/unidad. Un usuario con `reemplazos.revisar` puede:

- iniciar la revisión;
- guardar progresivamente grado E.U.S. numérico entre 1 y 99;
- seleccionar una clasificación de área activa;
- registrar si cumple normativa;
- agregar una observación administrativa;
- devolver con observación obligatoria;
- aprobar los antecedentes.

La aprobación exige grado, clasificación y una selección explícita de cumplimiento normativo. Se conserva quién revisó y cuándo.

### Documento generado

Desde `LISTA_GENERAR_DOCUMENTO`, Dompdf renderiza la plantilla Blade activa `REEMPLAZO_SOLICITUD_PDF` en tamaño A4. La primera y única generación actualmente soportada:

- crea un PDF privado con nombre físico ULID;
- calcula SHA-256 y tamaño;
- crea un `TramiteAdjunto` activo;
- crea `DocumentoGenerado` versión 1 y estado `VIGENTE`;
- transiciona a `DOCUMENTO_GENERADO` mediante el motor central.

El documento conserva en `metadata` un snapshot con versión/hash de plantilla, trámite, unidad, creador, funcionario y su vínculo laboral, reemplazante y antecedentes propuestos, ambos periodos, tipo, justificación, cálculo de cobertura y revisión administrativa. Cambios posteriores en personas o catálogos no alteran ese contexto emitido.

Si falla la operación, la transacción revierte los registros y el archivo escrito se elimina. No existe regeneración ni una versión posterior del documento generado.

### Formalización

La formalización actual es un **registro administrativo manual**, no una firma electrónica ni una integración con DocDigital. Registra una única `reemplazo_formalizaciones` por trámite con:

- documento generado vigente;
- antecedentes laborales del reemplazante;
- grado E.U.S. de la revisión;
- identificador externo opcional;
- observación opcional;
- actor y fecha de formalización;
- respaldo final opcional en PDF, DOC, DOCX, JPG o PNG, hasta 10 MiB.

Para solicitudes nuevas se usan los antecedentes laborales propuestos en el borrador. El formulario admite ingreso manual de esos datos solamente para registros legados que no los tengan.

La operación bloquea el trámite y ejecuta en una misma transacción la formalización, el adjunto opcional, el alta de dotación y la transición a `FORMALIZADA`. Ante un fallo se revierten los registros y se elimina el archivo recién escrito.

### Alta automática en dotación

La formalización incorpora al **reemplazante**, no al funcionario ausente, en la unidad del trámite y exactamente durante su periodo efectivo. Usa el estamento, profesión opcional, calidad contractual, cargo y grado formalizados.

El vínculo se crea con origen técnico `DOCUMENTO_FIRMADO` y referencia al trámite. Este nombre de origen expresa que el flujo recibió una evidencia administrativa; el sistema no verifica criptográficamente una firma.

La creación es idempotente: si ya existe un vínculo con `origen_tramite_id` para ese trámite se devuelve el mismo, y las restricciones únicas impiden duplicar tanto la formalización como el alta. Si existe una formalización pero falta su vínculo, se informa una inconsistencia en vez de crear silenciosamente otra realidad.

## 11. Pantallas principales

- **Login y perfil:** acceso por correo/RUT, recuperación y actualización de credenciales.
- **Dashboard:** resumen administrativo, panel de Gestión de Personas o “Mis trámites”, según permisos.
- **Mis trámites:** trámites abiertos de las unidades autorizadas y solicitudes que requieren atención.
- **Reemplazos:** creación, edición, detalle, adjuntos y reenvío de solicitudes devueltas.
- **Gestión de Personas:** bandeja de revisión, revisión administrativa, documento y formalización.
- **Dotación:** listado por alcance, filtros, clasificación institucional, resumen de Reemplazos, altas, continuidad, edición, cierre y ficha por persona.
- **Personas y cuentas:** listado, ficha consolidada, identidad, dotación, responsabilidades, cuenta, roles y accesos.
- **Estructura:** listado jerárquico, organigrama, unidades y tipos organizacionales.
- **Administración:** usuarios, roles/permisos, responsabilidades, accesos operativos y calidades contractuales.

La disponibilidad final de cada pantalla y acción depende de permisos y alcance; la presencia de una ruta no reemplaza la autorización del backend.

## 12. Estado actual del proyecto

### Implementado

- Autenticación por correo o RUT, cuentas activas y administración de credenciales.
- Roles y permisos administrables mediante Spatie.
- Personas únicas por RUT y asociación opcional con una cuenta.
- Árbol organizacional, organigrama, tipos y estructura institucional sembrada.
- Responsabilidades titular/subrogante con vigencia.
- Accesos operativos por unidad o unidad con descendientes.
- Dotación histórica, altas manuales y consulta por alcance.
- Núcleo transversal de trámites, correlativo anual, estados, transiciones e historial.
- Expediente privado con hash, versiones, descarga, visualización PDF y anulación lógica.
- Solicitud de Reemplazo completa desde borrador hasta formalización.
- Revisión administrativa y bandeja de Gestión de Personas.
- Generación de PDF con plantilla y snapshot.
- Alta idempotente del reemplazante en dotación al formalizar.
- Dashboards diferenciados y administración de catálogos actuales.

### No implementado o pendiente

- Módulo independiente de ausencias.
- Solicitudes de permisos administrativos y feriados por funcionarios.
- Registro y validación funcional de licencias médicas.
- Flujo de aprobaciones jerárquicas y prevención aplicada de autoaprobación.
- Constancia o integración con SIRH.
- Proceso de Horas Extraordinarias.
- Integración con DocDigital.
- Firma o visación electrónica.
- OCR o extracción automática de documentos.
- Antivirus de adjuntos.
- Configuración efectiva de documentos obligatorios para Reemplazos.
- Regeneración/versionado posterior del PDF generado.
- Desformalización o corrección transaccional posterior a una formalización.
- Notificaciones internas y hardening operativo de producción.

Las fuentes institucionales conservadas en `docs/` contienen antecedentes externos y plantillas de referencia. No deben interpretarse como evidencia de que sus procesos estén implementados.

## 13. Instalación desde cero

### Requisitos

- Windows con WSL2 y una distribución Ubuntu, o Linux equivalente.
- Git dentro de la distribución de trabajo.
- Docker Desktop con integración WSL habilitada para Ubuntu, o Docker Engine con Compose en Linux.
- Docker Compose v2 (`docker compose`).
- Puertos locales 8080, 3306 y 5173 disponibles, o valores alternativos en `.env`.

No trabaje dentro de la distribución `docker-desktop`; abra el repositorio desde Ubuntu.

### Clonar

```bash
git clone https://github.com/1CarlosFerreira/gestion-rrhh.git
cd gestion-rrhh
```

Seleccione la rama requerida si no es la rama predeterminada:

```bash
git switch feature/v2-base-limpia
```

### Variables de entorno

```bash
cp .env.example .env
```

Los valores de `.env.example` son únicamente de desarrollo. Revise al menos:

- `APP_URL` y `APP_PORT`;
- `APP_TIMEZONE=America/Santiago`;
- `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`;
- `DB_PORT_FORWARD` si el puerto 3306 está ocupado;
- `VITE_PORT` y, si el navegador usa otro host, `VITE_DEV_SERVER_URL`.

No agregue secretos reales al repositorio.

### Construir y levantar

```bash
docker compose config
docker compose up -d --build
docker compose ps
```

El servicio `app`, mediante `docker/php/entrypoint.sh`, realiza automáticamente en el primer arranque:

- creación de los directorios de `storage` y `bootstrap/cache`;
- copia de `.env.example` si `.env` no existe;
- `composer install` si falta `vendor/autoload.php`;
- generación de `APP_KEY` si está vacío.

El servicio `node` ejecuta `npm install && npm run dev`. El servicio `app` espera a que MySQL esté saludable antes de iniciar.

### Preparar Laravel y la base

Una vez que los contenedores estén activos:

```bash
docker compose exec app php artisan migrate --seed
```

Si se prefieren pasos separados:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

No es necesario ejecutar manualmente `composer install` ni `key:generate` después de un primer arranque exitoso. Si necesita repetirlos deliberadamente:

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### Frontend

El modo de desarrollo queda activo con `docker compose up -d` porque el servicio `node` ejecuta Vite. Para instalar o actualizar dependencias explícitamente y compilar assets:

```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Acceso local

Con `.env.example`:

- Aplicación: <http://localhost:8080>
- Vite: <http://localhost:5173>
- Nginx es el único punto de entrada HTTP de la aplicación.

### Credencial inicial de desarrollo

`AdminUserSeeder` crea exclusivamente para desarrollo:

```text
Usuario: admin@example.test
RUT: 11111111-1
Contraseña: password
```

La cuenta y la Persona son ficticias. No use estas credenciales ni estos valores en producción. El seeder actual no crea una cuenta de jefatura o solicitante.

### Permisos de archivos en WSL2

El entrypoint prepara `storage` y `bootstrap/cache` con propietario `www-data`, directorios `775` y archivos `664`; no use `chmod 777`. Si un volumen antiguo conserva permisos incorrectos:

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app find storage bootstrap/cache -type d -exec chmod 775 {} \;
docker compose exec app find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Después reinicie la aplicación:

```bash
docker compose restart app nginx
```

## 14. Operación diaria

```bash
# Levantar servicios
docker compose up -d

# Reconstruir imágenes cuando cambie Dockerfile o dependencias del sistema
docker compose up -d --build

# Detener servicios sin eliminar datos
docker compose down

# Estado y logs
docker compose ps
docker compose logs -f
docker compose logs -f app nginx db node

# Artisan y Composer
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app composer install

# Shells de diagnóstico
docker compose exec app sh
docker compose exec node sh
```

Para ejecutar otro comando Artisan use siempre:

```bash
docker compose exec app php artisan <comando>
```

Para npm, use el contenedor Node:

```bash
docker compose run --rm node npm <comando>
```

## 15. Base de datos

La base usa MySQL 8.4 en el servicio `db`, con `utf8mb4` y `utf8mb4_unicode_ci`. Laravel se conecta dentro de Compose mediante `DB_HOST=db`; el puerto publicado en el host se controla con `DB_PORT_FORWARD`.

El nombre, usuario y contraseña provienen de `.env`. Los valores predeterminados son locales y no son credenciales de producción.

Las migraciones están en `database/migrations` y los seeders idempotentes en `database/seeders`. `DatabaseSeeder` carga permisos/roles, catálogos, estructura institucional, flujo de Reemplazos, tipos documentales, plantilla PDF y administrador de desarrollo.

Para reconstruir completamente una base **solo de desarrollo**:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

> **Advertencia:** `migrate:fresh` elimina todas las tablas y destruye todos los datos de la base seleccionada. Nunca lo ejecute contra un ambiente con información que deba conservarse.

`docker compose down -v` también elimina los volúmenes de desarrollo, incluida la base de datos. Úselo únicamente cuando esa pérdida sea intencional.

## 16. Pruebas y calidad

La suite usa PHPUnit. Durante pruebas, `phpunit.xml` configura SQLite en memoria, cache/sesiones en memoria y cola síncrona.

```bash
# Suite completa
docker compose exec app php artisan test

# Archivo específico
docker compose exec app php artisan test tests/Feature/DotacionHistoricaTest.php

# Filtro por nombre de prueba
docker compose exec app php artisan test --filter=nombre_de_la_prueba

# Estilo PHP
docker compose exec app ./vendor/bin/pint --test

# Compilación frontend
docker compose run --rm node npm run build

# Errores de espacios o marcadores de conflicto en el diff
git diff --check
```

Las pruebas cubren autenticación, RUT, estructura, responsabilidades, accesos, dotación, roles, dashboards, autorización por unidad, expediente y el flujo completo de Reemplazos.

## 17. Storage y documentos

El disco `private` apunta a `storage/app/private`, tiene `serve=false` y lanza excepciones de escritura. Los adjuntos y documentos generados se organizan bajo `tramites/{public_id}/`.

No ejecute `php artisan storage:link` para exponer documentos de RRHH. Esa orden enlaza el disco público, pero los documentos del sistema pertenecen al disco privado y deben seguir pasando por los controladores autorizados.

Antes de desplegar se debe definir una política institucional de respaldo, retención, cifrado y antivirus para el volumen privado. El repositorio no incluye esas capacidades de infraestructura.

## 18. Solución de problemas frecuentes

### Docker daemon no disponible

Si `docker compose ps` devuelve un error de permisos o conexión:

- confirme que Docker Desktop o Docker Engine esté iniciado;
- en Windows, habilite la integración WSL para la distribución Ubuntu usada;
- ejecute `uname -a` y `cat /etc/os-release` para confirmar que está en Ubuntu y no en `docker-desktop`.

### Puertos ocupados

Cambie en `.env` `APP_PORT`, `DB_PORT_FORWARD` o `VITE_PORT`. `APP_URL` debe usar el mismo puerto que `APP_PORT`. Reinicie Compose después del cambio.

### El contenedor de aplicación no inicia

```bash
docker compose ps
docker compose logs app db
docker compose exec app php-fpm -t
```

Revise primero la salud de MySQL, porque `app` depende de ella.

### Assets o Vite no cargan

```bash
docker compose logs node
docker compose restart node
docker compose run --rm node npm install
```

Compruebe `APP_URL`, `VITE_PORT` y `VITE_DEV_SERVER_URL` si el navegador no accede mediante `localhost`.

### Cachés de Laravel desactualizadas

```bash
docker compose exec app php artisan optimize:clear
```

### Errores de escritura

Use las instrucciones de permisos de la sección de instalación y confirme que el volumen del proyecto está montado en `/var/www/html`. No solucione permisos con `777`.

## 19. Estructura relevante del repositorio

```text
app/
├── Actions/             Casos de uso transaccionales
├── Contracts/           Contratos del motor de trámites
├── Enums/               Valores de dominio persistidos como strings
├── Http/Controllers/    Coordinación HTTP
├── Http/Requests/       Validación y autorización de entrada
├── Models/              Modelos y relaciones Eloquent
├── Policies/            Autorización de recursos
├── Services/            Reglas de dominio y resolución de alcance
└── Support/             Utilidades, incluida normalización de RUT

database/
├── migrations/          Esquema vigente
└── seeders/             Roles, catálogos, estructura y datos de desarrollo

resources/
├── assets/              Recursos de la plantilla institucional
├── css/ y js/           Entrada de Vite, Tailwind y Alpine
└── views/               Blade de pantallas y PDF

routes/                  Rutas web, autenticación y consola
tests/                   Pruebas Feature y Unit
docs/                    Fuentes institucionales externas conservadas
docker/                  PHP, entrypoint y configuración de Nginx
```

Los archivos `.docx` de `docs/` son fuentes o plantillas institucionales. El comportamiento vigente y las instrucciones operativas están documentados en este README y deben confirmarse siempre contra el código.

## 20. Convenciones para continuar desarrollando

- Mantener `tramites` como raíz transversal; los procesos agregan tablas de detalle.
- No usar ENUM de MySQL para estados de trámite. Los estados y transiciones son configurables en tablas.
- No asignar directamente el estado de un trámite existente. Usar `TransicionarTramite` para validar, bloquear, cambiar y registrar historial.
- Mantener `tramite_historial` inmutable y agregar eventos para operaciones relevantes.
- Aplicar autorización en backend. La capacidad efectiva combina permisos y alcance organizacional, salvo permisos globales explícitos.
- Mantener separados RBAC, acceso operativo, dotación y responsabilidad institucional.
- Centralizar la resolución de alcance; no duplicarla en controladores o vistas.
- Preservar historia: cerrar o desactivar registros usados en vez de eliminarlos o reescribir su identidad.
- Mantener archivos de RRHH en storage privado, con nombre aleatorio, MIME, tamaño, SHA-256, actor y versión.
- No confiar en IDs de usuario, unidad, rol o estado enviados por el cliente sin validarlos.
- No incorporar al reemplazante a dotación durante el borrador, envío, revisión o generación. El alta ocurre al formalizar.
- Mantener formalización, adjunto final, alta de dotación y transición dentro del flujo transaccional existente.
- No describir el respaldo final como firma electrónica; actualmente es evidencia administrativa manual.
- Guardar horas como minutos enteros si se implementa Horas Extraordinarias y no extraer automáticamente datos de PDF sin una decisión institucional nueva.
- Mantener controladores pequeños, validación en Form Requests, autorización en Policies/Gates y negocio en Actions/Services.
- Ejecutar pruebas relevantes, suite completa cuando corresponda, Pint y compilación frontend antes de cerrar una entrega.
- No inventar catálogos, requisitos documentales, reglas SIRH, firma electrónica ni integraciones externas sin una fuente institucional confirmada.
