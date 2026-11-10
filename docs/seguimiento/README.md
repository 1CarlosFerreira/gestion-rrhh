# Seguimiento y continuidad NEXO — Gestión RRHH

Adaptación local de la base documentada NEXO v0.3 y de su procedimiento de
seguimiento en laboratorio-ia. No define NEXO v0.4. El [README principal](../../README.md)
continúa como documento maestro; [AGENTS.md](../../AGENTS.md) y los perfiles
[Arquitecto](../../agentes/arquitecto.md), [Desarrollador](../../agentes/desarrollador.md)
y [Tester](../../agentes/tester.md) mantienen sus reglas técnicas y metodológicas.
Este procedimiento organiza la continuidad sin ampliar permisos ni crear agentes.

## Ficha canónica e identificación

Cada trabajo usa un identificador único `NEXO-RRHH-AAAA-NNN`: año de apertura y
correlativo de tres cifras, libre tras revisar los identificadores existentes de
ese año. No reutilizar identificadores ni cambiarlos al cambiar el estado.
Con autorización explícita para crearla, la única ficha canónica se ubica en
`docs/seguimiento/trabajos/NEXO-RRHH-AAAA-NNN.md`, usando la
[plantilla](plantilla.md). Esa ruta es una convención, no una ficha existente.
Crear la carpeta y la ficha requiere permiso para esas escrituras.

Arquitecto, Desarrollador y Tester trabajan sobre la misma ficha dentro de sus
permisos. No crear copias por rol o sesión ni índices paralelos de estados.
Referenciar el plan canónico por identificador y versión sin duplicarlo como otro
plan editable. Conservar un resumen vigente e hitos, no transcripciones completas.
Sin permiso para escribir seguimiento, registrar la entrega en conversación.

Distinguir siempre:

- **Hechos comprobados:** observación, fecha, método, material y evidencia.
- **Declaraciones:** fuente humana o entrega que las afirma, aún sin verificación propia.
- **Supuestos y decisiones pendientes:** incertidumbre, responsable y efecto bloqueante.

La ficha es memoria y puede estar desactualizada; no acredita por sí sola permiso,
ejecución, funcionamiento ni aceptación. Ajustar detalle a la proporcionalidad de
AGENTS.md sin omitir controles; justificar «no aplica» y no usarlo para verificaciones
aplicables que no se pudieron ejecutar.

## Estado, condición y responsabilidades

Mantener por fase el flujo de AGENTS.md:

`propuesta → pendiente de aprobación → autorizada → en implementación → en verificación → pendiente de aceptación → aceptada`

Propuesta y aprobación corresponden a planificación. El estado registra lo ocurrido,
no habilita acciones. Registrar separadamente la condición `activa`, `suspendida` o
`bloqueada`; una suspensión no borra el estado ni los resultados anteriores.
Indicar causa, trabajo incompleto, efectos pendientes, decisión necesaria y condición
de reanudación. No inferir permiso para revertir, limpiar archivos o detener servicios.

El Arquitecto propone alcance, plan, alternativas, riesgos y criterios. El Desarrollador
implementa y documenta las verificaciones permitidas. El Tester aporta evidencia
independiente y recomendación favorable, favorable con observaciones o desfavorable.
El responsable humano autoriza, decide y acepta. Los perfiles no crean delegaciones.

## Autorizaciones humanas y vigencia

Registrar cada autorización con identificador único, referencia verificable al mensaje
humano y fecha disponible, resumen fiel, plan y versión, fase, archivos permitidos,
acciones, verificaciones y entorno, correcciones cubiertas, exclusiones y condición
de término. No inventar fechas, citas ni aprobaciones; indicar referencias inaccesibles.

Distinguir permisos de **análisis, implementación, pruebas, migraciones, commit y
push**. Ninguno concede los demás por implicación. Instalaciones, seeders, acceso a
producción y operaciones destructivas también requieren autorización explícita.
Evaluar los efectos sobre archivos, cachés, bases y servicios antes de verificar.
Una revisión documental no habilita Laravel, Pint, compilación, Docker ni migraciones,
y no acredita el funcionamiento de la aplicación.

Antes de cada acción contrastar su cobertura y entorno con el mensaje humano y las
instrucciones posteriores. Registrar situación: vigente, agotada, vencida, revocada
o sustituida, con condición de término y referencia del cambio cuando exista.
Conservar antecedentes; no presentar permisos históricos como actuales. Detener lo
afectado al agotarse su alcance, vencer, revocarse o sustituirse. Si no es comprobable
la vigencia o hay ambigüedad, pedir aclaración y continuar solo trabajo independiente
ya autorizado. El silencio, una recomendación o un cambio de perfil no son permisos.

Un cambio de sesión no amplía permisos ordinarios: comprobar de nuevo su vigencia.
Ante fallos del sandbox, detener la operación, registrar el error saneado y solicitar
autorización específica para la acción y entorno excepcionales. No eludir el control,
convertir la excepción en permiso permanente ni heredar excepciones de otras sesiones.
Guardar o actualizar la ficha también exige autorización para ese archivo.

## Inicio, actualización y recuperación de contexto

1. Leer solicitud, README maestro, AGENTS.md, perfil aplicable y, si existe y está
   autorizada su consulta, la ficha canónica y la última transferencia.
2. Contrastar obligatoriamente la ficha con Git real: `git status`,
   `git branch --show-current`, `git log -1 --oneline` y `git remote -v` son lecturas
   locales. Identificar commit base completo, rama, cambios previos, cambios propios
   y archivos sin seguimiento. Confirmar Ubuntu nativo o WSL2 Ubuntu.
3. Comparar el material entregado con el actual, incluyendo diff y contenido nuevo.
   No sobrescribir trabajo ajeno. Si cambió el material, reevaluar evidencia y
   conclusiones afectadas antes de reutilizarlas.
4. Comprobar objetivo, alcance, plan y versión, fase, criterios observables, riesgos,
   pendientes y permisos para la siguiente acción y sus verificaciones.
5. Ante discrepancias entre ficha, Git, entrega o autorización, registrar lo observado
   y su fuente sin corregir retrospectivamente hechos. Detener acciones dependientes;
   solicitar decisión humana si cambia alcance, diseño, base o permiso. Continuar solo
   trabajo independiente autorizado. No reconciliar mediante reset, pull o fetch implícitos.
6. Actualizar solo con permiso en cada hito: autorización, decisión, implementación,
   verificación, suspensión o transferencia. Registrar fecha, rol, acciones realmente
   ejecutadas, resultado y evidencia; conservar antecedentes corregidos o sustituidos.

## Evidencia, Git y conservación

Cada criterio tiene ID, condición observable, método y entorno, resultado `cumplido`,
`incumplido` o `no verificado`, y evidencia o motivo. Separar evidencia documental
(contenido, coherencia, enlaces y diff) de evidencia técnica de aplicación (comandos,
entorno y resultados autorizados). No convertir una declaración de ejecución en una
prueba comprobada. Registrar verificaciones omitidas y sus limitaciones.

Identificar el material exacto por commit o diff saneado y contenido de archivos
sin seguimiento; usar huellas cuando aporten trazabilidad. El commit base no identifica
cambios posteriores. No incluir la huella de la ficha dentro de sí misma; entregar la
identificación del material en conversación o en otra referencia autorizada.

Distinguir conservación **local**, **en commit** y **publicada en remoto**:

- Los archivos sin seguimiento y cambios sin commit solo existen localmente en ese
  estado; documentar su ubicación y riesgo de pérdida.
- Un commit autorizado conserva una versión local. Registrar su hash y material;
  no crearlo únicamente para identificar una entrega sin permiso.
- Publicación remota requiere push autorizado y evidencia de su resultado. Una URL
  de remoto configurada no demuestra publicación. No ejecutar push, pull ni fetch
  como parte implícita del seguimiento.

Conservar ficha, ruta, hitos y referencias de evidencia al cerrar. Indicar evidencia
no disponible, ubicación autorizada y limitaciones de conservación; no prometer
respaldos ni políticas de retención que no existen. Cualquier copia, traslado o
eliminación requiere cobertura de permiso y protección de los datos.

## Protección de información

No incluir datos personales reales de funcionarios o pacientes, RUT, antecedentes
clínicos o laborales identificables, documentos de RRHH, credenciales, tokens,
secretos ni contenido de `.env` en fichas, nombres, diffs o capturas. Usar datos
ficticios, identificadores neutros y salidas mínimas saneadas. No copiar volcados,
logs completos ni conversaciones con información sensible.

Referenciar evidencia sensible mediante identificadores neutros en un medio autorizado,
con acceso restringido; no trasladarla al repositorio ni enviarla a servicios externos
sin autorización y controles adecuados. Revisar contenido y diff antes de conservación
o publicación autorizadas; `.gitignore` no protege Markdown ni archivos ya seguidos.
Ante exposición, detener difusión e informar sin reproducir el dato; solicitar permiso
para remediar. Borrar una línea no elimina copias ni historial Git.

## Transferencia y cierre

Cada transferencia incluye trabajo, fase, estado y condición, perfil que entrega,
plan y versión, autorización aplicable, commit base, rama, Git observado y material
exacto revisado, cambios previos y propios, resultados por criterio, comandos,
evidencia, limitaciones, hallazgos, riesgos, siguiente responsable y acción esperada.
Los defectos regresan al Desarrollador solo con permiso de corrección; cambios de
alcance o diseño vuelven a planificación y aprobación. La transferencia no autoriza
al siguiente perfil ni obliga a crear una ficha sin permiso.

Al terminar la implementación, entregar al Tester para verificación independiente
cuando esté autorizada. Tras su recomendación, mantener pendiente de aceptación
hasta la decisión humana expresa, registrada con referencia y alcance aceptado.
Cerrar una fase o el trabajo solo según esa aceptación; conservar pendientes y
condiciones acordadas. La aceptación no equivale a commit o publicación remota ni
autoriza otra fase. Detenerse en el límite de la entrega.
