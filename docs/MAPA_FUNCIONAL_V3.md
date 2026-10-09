# MAPA FUNCIONAL V3

## Plataforma Interna de Gestión - Gestión RRHH

### Hospital Dr. Humberto Elorza Cortés de Illapel

**Estado:** Definición funcional consolidada previa al diseño técnico
V3.\
**Propósito del documento:** servir como fuente funcional para la
evolución de la implementación V2 hacia V3 y como entrada para el
análisis técnico de Codex.

## Convenciones

-   🟢 **CONFIRMADO:** regla funcional acordada y que debe respetar la
    implementación.
-   🔵 **PROPUESTA DE DISEÑO:** decisión conceptual útil, pero cuya
    representación técnica aún debe definirse.
-   🟡 **POR CONFIRMAR:** regla o detalle institucional que requiere
    validación antes de implementarse como restricción definitiva.
-   ⚪ **FUTURO:** evolución deliberadamente fuera del alcance
    inmediato.

------------------------------------------------------------------------

# 1. Propósito y alcance

La Plataforma Interna de Gestión tiene como propósito centralizar y dar
trazabilidad a información y procesos administrativos relacionados con
**personas, estructura organizacional, dotación, solicitudes, trámites,
expedientes y documentos** del Hospital.

La plataforma busca facilitar el trabajo interno de las unidades y de
Gestión de Personas, manteniendo un historial comprensible de las
actuaciones realizadas y permitiendo relacionar personas, unidades,
trámites y documentación sin duplicar innecesariamente información
mantenida por sistemas institucionales externos.

La plataforma **no pretende reemplazar** SIRH, DocDigital, FirmaGob,
remuneraciones, control de asistencia ni convertirse en un ERP
hospitalario.

``` text
PLATAFORMA INTERNA DE GESTIÓN
│
├── Personas
├── Organización
├── Dotación / destinación
├── Solicitudes y trámites
├── Expedientes
├── Documentos
├── Formalizaciones
└── Trazabilidad
```

🟢 SIRH y DocDigital continúan siendo sistemas externos.

------------------------------------------------------------------------

# 2. Principios funcionales

🟢 La plataforma se organiza alrededor de **personas + organización +
procesos + documentos + historia**.

🟢 Un trámite representa un proceso administrativo concreto.

🟢 La identidad de una persona no depende de que posea una cuenta de
usuario.

🟢 El usuario que opera el sistema no necesariamente es la autoridad
institucional que solicita, representa o autoriza.

🟢 La unidad solicitante representa el contexto institucional desde el
cual se realiza la solicitud y debe seleccionarse únicamente dentro del
acceso operativo vigente del usuario.

🟢 La unidad de origen de una necesidad no necesariamente es la unidad
donde desempeñará funciones la persona contratada.

🟢 Unidad solicitante, unidad origen y unidad destino son contextos
organizacionales independientes y deben validarse por separado.

🟢 La dotación/destinación funcional y la cobertura contractual son
conceptos diferentes.

🟢 La información histórica no debe alterarse cuando posteriormente
cambien personas, unidades, autoridades, plantillas o datos maestros.

🟢 El sistema no debe inferir hechos que no conoce. Un período sin
cobertura registrada no significa automáticamente deuda, trabajo impago,
ausencia efectiva u otra conclusión administrativa.

🟢 Las reglas críticas deben validarse en backend y no depender
exclusivamente de la interfaz, botones o JavaScript.

🟢 La plataforma debe impedir contradicciones administrativas conocidas,
pero no debe inventar reglas institucionales que no hayan sido
confirmadas.

------------------------------------------------------------------------

# 3. Actores y estructura organizacional

Los actores funcionales principales son:

-   Funcionario/persona.
-   Usuario del sistema.
-   Jefatura o responsable organizacional.
-   Administrativo/secretaría.
-   Gestión de Personas/RRHH.
-   Administrador del sistema.

Estos actores **no deben transformarse necesariamente en roles rígidos
uno a uno**. La autorización efectiva dependerá de permisos, alcance
organizacional, responsabilidades, estado del proceso y reglas del
trámite.

La estructura organizacional debe soportar unidades jerárquicas.

``` text
Hospital
│
├── Subdirección A
│   ├── Unidad A
│   └── Unidad B
│
└── Subdirección B
    ├── Unidad C
    └── Unidad D
```

Las responsabilidades organizacionales deben mantenerse separadas de los
roles técnicos.

``` text
RESPONSABILIDAD
├── TITULAR
└── SUBROGANTE
```

La responsabilidad puede tener vigencia temporal y, cuando corresponda,
capacidad de aprobación.

🟢 Las responsabilidades `TITULAR` y `SUBROGANTE` forman parte de la
resolución de autoridades institucionales.

🟢 No existe una regla universal según la cual el `SUBROGANTE` sustituya
al `TITULAR` para todo acto administrativo. La autoridad aplicable puede
depender del tipo de solicitud, actuación o documento.

🟡 Las reglas específicas de precedencia entre titular y subrogante para
cada acto deben confirmarse institucionalmente antes de implementarlas.

🟢 Reemplazar contractualmente a una persona que ejerce una jefatura
**no transmite automáticamente su titularidad, subrogancia, funciones o
autoridad organizacional**.

------------------------------------------------------------------------

# 4. Personas, usuarios, dotación y cobertura contractual

## 4.1 Persona ≠ Usuario

**Persona** representa a una persona natural dentro del dominio
administrativo.

Puede participar como funcionario, funcionario origen, persona
propuesta, reemplazante, contratado, responsable organizacional u otro
participante.

**Usuario** representa una cuenta capaz de ingresar al sistema y
realizar acciones.

``` text
PERSONA ≠ USUARIO
```

Una persona puede existir sin usuario.

El usuario permite determinar quién realizó una acción, cuándo, con qué
permisos y dentro de qué alcance; no determina por sí solo quién es la
autoridad institucional representada.

## 4.2 Destinación funcional ≠ cobertura contractual

La plataforma debe poder representar dónde desempeña funciones una
persona.

Ejemplo:

``` text
Carlos Ferreira
      ↓
Informática
```

Esa destinación puede mantenerse de forma continua aunque existan
múltiples períodos contractuales consecutivos.

``` text
DESTINACIÓN FUNCIONAL
Informática
════════════════════════════════════>

COBERTURA CONTRACTUAL
Contrato A │ Contrato B │ Contrato C
───────────┼────────────┼────────────>
```

🟢 Una nueva contratación no debe crear necesariamente una nueva
destinación funcional idéntica si la persona continúa desempeñándose en
la misma unidad.

🟡 Debe confirmarse con Gestión de Personas el significado institucional
exacto de **dotación**, **destinación** y **vínculo**, y su relación con
SIRH.

## 4.3 Profesión, estamento, grado y función

🟢 Profesión, estamento, grado y cargo/función son conceptos propios de
la persona contratada y/o de las condiciones finales de contratación.

🟢 El funcionario origen no transmite automáticamente esos datos al
reemplazante.

------------------------------------------------------------------------

# 5. Seguridad, roles, responsabilidades y alcances

La autorización efectiva se entiende conceptualmente como:

``` text
PERMISO
+
ALCANCE ORGANIZACIONAL
+
RESPONSABILIDAD
+
ESTADO / CONTEXTO
+
REGLAS DEL PROCESO
        ↓
ACCIÓN PERMITIDA
```

El **rol** agrupa capacidades generales.

El **permiso** determina qué tipo de acción puede realizarse.

El **acceso operativo** determina sobre qué unidades puede actuar el
usuario.

La **responsabilidad** representa autoridad organizacional.

🟢 Acceso operativo ≠ responsabilidad.

🟢 Administrador del sistema ≠ autoridad institucional.

🟢 Ver ≠ editar ≠ revisar ≠ autorizar ≠ formalizar.

🟢 Una subrogancia debe representarse como una responsabilidad temporal
cuando corresponda, no como un cambio artificial de rol global.

## 5.1 Usuario que actúa ≠ autoridad representada

``` text
USUARIO QUE ACTÚA
        ≠
AUTORIDAD REPRESENTADA
```

Ejemplo:

``` text
Secretaria de Subdirección Administrativa
        │
        │ registra
        ↓
Solicitud
        │
        ├── Registrador:
        │   Secretaria
        │
        ├── Unidad solicitante:
        │   Subdirección Administrativa
        │
        └── Autoridad institucional:
            Subdirector Administrativo vigente
            (resuelto automáticamente)
```

El historial conserva:

``` text
Registrado por:
Secretaria
```

mientras el documento puede indicar, según corresponda:

``` text
DE:
Subdirector
```

🟢 La identidad mostrada como solicitante, remitente o autoridad en un
documento no debe derivarse automáticamente del usuario creador.

🟢 La autoridad institucional no se selecciona manualmente. Una vez
determinada la unidad solicitante, el sistema debe resolverla utilizando
la estructura organizacional, las responsabilidades vigentes, la fecha o
contexto del trámite y las reglas aplicables al acto.

🟢 El usuario operador no puede atribuir una autoridad mediante datos
enviados desde el formulario.

🟢 La autoridad institucional puede corresponder a una Persona que no
posea una cuenta User.

🟢 El historial conserva siempre al usuario real que ejecutó la acción.
La autoridad representada no sustituye ni altera la identidad del actor.

## 5.2 Alcance organizacional de la solicitud

🟢 El usuario que confecciona una solicitud selecciona la unidad
solicitante únicamente entre las unidades comprendidas en su acceso
operativo vigente.

Los alcances existentes se aplican de la siguiente forma:

``` text
SOLO_UNIDAD
→ únicamente la unidad autorizada

UNIDAD_Y_DESCENDIENTES
→ unidad autorizada + descendientes vigentes
```

🟢 Para un usuario operativo normal, la unidad solicitante, la unidad de
origen y la unidad de destino deben estar cada una dentro de su alcance.
La validez de una de ellas no autoriza automáticamente las demás.

🟢 Cada Subdirección gestiona solicitudes de su propio ámbito
organizacional. Sus usuarios operativos no pueden gestionar libremente
personas o unidades pertenecientes a otra Subdirección.

🟢 Este alcance debe aprovechar los accesos operativos, la jerarquía
organizacional y los alcances `SOLO_UNIDAD` y
`UNIDAD_Y_DESCENDIENTES`. No deben crearse roles específicos por unidad.

🟢 Los selectores de unidades y personas deben mostrar normalmente solo
alternativas permitidas para la operación. Este filtrado es una ayuda de
interfaz y no reemplaza la autorización del backend.

🟢 El backend debe validar nuevamente cada unidad y persona recibida. La
manipulación de una petición HTTP no puede ampliar el alcance autorizado.

## 5.3 Gestión de Personas y alcance transversal

🟢 Gestión de Personas cumple una función transversal. Sus usuarios
autorizados pueden consultar y gestionar solicitudes provenientes de
todas las Subdirecciones cuando sus funciones lo requieren.

``` text
ALCANCE GLOBAL ≠ PERMISO GLOBAL
```

La visibilidad hospitalaria no concede automáticamente capacidad para
editar, revisar, aprobar, generar documentos, formalizar, administrar
usuarios o modificar la estructura organizacional.

🟢 Cada acción continúa dependiendo de permiso, alcance, estado o
contexto y reglas del proceso.

🟢 `tramites.ver_todos` no constituye un bypass universal de las demás
reglas de autorización.

------------------------------------------------------------------------

# 6. Trámites, unidades y expedientes

## 6.1 Trámite

El trámite es el contenedor transversal de un proceso administrativo.

``` text
TRÁMITE
│
├── Identificación
├── Tipo
├── Estado
├── Contexto organizacional
├── Participantes
├── Datos específicos
├── Expediente
├── Documentos generados
├── Formalización
└── Historial
```

## 6.2 Unidades con significado explícito

No debe existir una única "unidad del trámite" cuyo significado cambie
según el contexto.

Un proceso puede necesitar distinguir:

``` text
Unidad solicitante
Unidad origen
Unidad destino
Unidad revisora
```

No todos los trámites requieren todas ellas, pero cuando existan su
significado debe ser explícito.

🟢 La **unidad solicitante** es el contexto institucional desde el cual
se realiza la solicitud. No equivale a la unidad origen, la unidad
destino, el usuario registrador ni la autoridad institucional.

🟢 La **unidad origen** identifica el contexto que sustenta la necesidad
cuando el proceso lo requiere.

🟢 La **unidad destino** identifica dónde se desempeñarán las funciones o
se materializará el resultado correspondiente.

La autoridad institucional se resuelve desde el contexto de la unidad
solicitante y las reglas del acto; no se recibe como una selección libre
del formulario.

## 6.3 Expediente

Cada trámite posee un expediente.

``` text
EXPEDIENTE
│
├── Antecedentes
├── Adjuntos
├── Documentos generados
├── Evidencias posteriores
└── Historial documental
```

El expediente pertenece al proceso y no debe confundirse con un futuro
repositorio documental general de la persona o de la unidad.

🟢 Los archivos de RRHH deben mantenerse privados y descargarse
únicamente mediante autorización del sistema.

------------------------------------------------------------------------

# 7. Solicitud de contrato

El proceso principal deja de entenderse únicamente como "Solicitud de
Reemplazo" y pasa conceptualmente a:

``` text
SOLICITUD DE CONTRATO
│
├── PERMANENTE
└── TRANSITORIA
```

El reemplazo es una situación dentro de las contrataciones transitorias
y no la identidad completa de la plataforma.

## 7.1 Solicitud de contrato permanente

Una solicitud permanente no depende de la ausencia de otro funcionario.

Conceptualmente puede considerar:

``` text
SOLICITUD PERMANENTE
│
├── Persona a contratar
├── Unidad destino
├── Cargo / función
├── Profesión
├── Estamento
├── Grado
├── Antecedentes
└── Condiciones correspondientes
```

🟡 Los requisitos, documentos, autorizaciones, condiciones y workflow
exacto de contratación permanente deben ser confirmados con Gestión de
Personas.

V3 debe soportar este tipo funcionalmente sin inventar reglas todavía no
definidas.

## 7.2 Solicitud de contrato transitoria

La solicitud transitoria contiene el antecedente que origina la
necesidad.

``` text
SOLICITUD TRANSITORIA
│
├── Funcionario origen
├── Unidad origen
├── Motivo
├── Período origen
├── Justificación
│
├── ¿Existe reemplazante propuesto?
│      ├── SÍ
│      └── NO
│
└── Gestión de Personas
```

🟢 No se construirá por ahora un módulo independiente de "Gestión de
Ausencias". La situación temporal del funcionario origen forma parte de
la propia solicitud.

🟢 **RF-15 — Registro inicial:** la jefatura o el administrativo
autorizado registra el respaldo al crear la solicitud, sujeto a permisos
y alcance organizacional efectivos. Gestión de Personas revisa, corrige
y valida los antecedentes antes de comprometerlo (véase 8.7).

## 7.3 Transitoria con reemplazante propuesto

La solicitud puede incluir una persona propuesta.

``` text
Funcionario origen:
Juan Pérez

Período origen:
01–30 octubre

Reemplazante propuesto:
Carlos Ferreira

Período contractual:
01–20 octubre

Destino:
Informática
```

🟢 Persona propuesta ≠ persona finalmente validada/contratada.

Gestión de Personas puede revisar y determinar las condiciones finales
antes del resultado administrativo.

## 7.4 Transitoria sin reemplazante

🟢 La ausencia de reemplazante **no hace incompleta la solicitud**.

Debe ser posible:

``` text
Crear
  ↓
Enviar
  ↓
Gestión de Personas recibe
```

aunque:

``` text
Reemplazante propuesto:
NINGUNO
```

Posteriormente podrá determinarse una persona si las reglas
institucionales lo permiten.

🟢 El envío sin persona propuesta y su eventual incorporación posterior
siguen las reglas de compromiso y disponibilidad de 8.7 y 8.8.

## 7.5 Funcionario origen ≠ persona contratada

El funcionario que origina el respaldo y la persona contratada son
participantes distintos.

El origen no transmite automáticamente cargo, funciones, grado,
profesión, responsabilidad ni unidad de desempeño.

Ejemplo de referencia:

``` text
FUNCIONARIO ORIGEN
Profesional
Otra unidad
       ↓
respalda período
       ↓
PERSONA CONTRATADA
Profesional
Grado propio
Destino: Informática
```

## 7.6 Origen ≠ destino

🟢 Una contratación transitoria puede tener:

``` text
Unidad origen:
Abastecimiento

Unidad destino:
Informática
```

El origen explica el respaldo administrativo.

El destino explica dónde desempeñará funciones la persona contratada.

🟢 Para un usuario operativo normal, origen y destino deben validarse
independientemente contra su alcance vigente, además de validar la unidad
solicitante.

Ejemplo permitido dentro de un mismo ámbito autorizado:

``` text
Unidad solicitante: Subdirección Administrativa
Unidad origen: Abastecimiento
Unidad destino: Informática
```

Una unidad solicitante válida no habilita un destino perteneciente a una
Subdirección fuera del alcance del usuario.

------------------------------------------------------------------------

# 8. Períodos, exclusividad y solapamientos

## 8.1 Período origen y período contractual

Toda solicitud transitoria debe distinguir:

``` text
PERÍODO ORIGEN
desde / hasta

PERÍODO CONTRACTUAL
desde / hasta
```

🟢 El período contractual debe estar completamente contenido dentro del
período que lo respalda.

``` text
Origen:
01 ───────────────── 30

Contrato:
     05 ─────── 20
     ✓
```

No puede ocurrir:

``` text
Origen:
01 ─────────── 30

Contrato:
          25 ───────── 10 noviembre
                         ❌
```

## 8.2 Sin solapamiento del funcionario origen

🟢 **RF-11 — Superposición de respaldos:** se bloquea el registro de
respaldos transitorios con períodos superpuestos para un mismo
funcionario origen, aunque los motivos sean distintos. Se usan días
calendario y ambos extremos son inclusivos. La validación debe resistir
la concurrencia entre solicitudes distintas (véase 12.8).

``` text
Juan

Solicitud A: 01–20 octubre
Solicitud B: 15–30 octubre  ❌

Solicitud A: 01–20 octubre
Solicitud B: 21–31 octubre  ✓
```

Esta regla corresponde a un **bloqueo**, no a una advertencia. La
reutilización del *mismo* respaldo liberado se rige por 8.7: no equivale
a registrar otro respaldo superpuesto. La identidad administrativa
verificable de antecedentes externos duplicados sigue pendiente (18).

## 8.3 Sin solapamiento de la persona contratada o reservada

🟢 Una persona no puede mantener contratos transitorios cuyos períodos
se superpongan, aunque provengan de respaldos distintos.

``` text
Juan  → Carlos 01–20
Pedro → Carlos 15–30   ❌
```

Sí pueden existir contratos consecutivos:

``` text
Juan  → Carlos 01–20
Pedro → Carlos 21–31   ✓
```

🟢 Una persona puede encadenar contratos provenientes de distintos
respaldos, pero no mantener contratos transitorios simultáneos.

🟢 Desde la aprobación, la persona propuesta, si existe, tiene una
reserva de fechas. Su disponibilidad debe comprobarse frente a reservas
vigentes y coberturas contractuales superpuestas de esa persona. La
reserva de propuesta no demuestra por sí sola una contratación
finalmente formalizada (véanse 8.8 y 11).

## 8.4 Exclusividad del respaldo

🟢 Un respaldo transitorio comprometido puede cubrir como máximo a
**una persona a la vez**. No se distribuye entre personas ni solicitudes
simultáneas. El cambio controlado de persona antes del documento y la
liberación formal de una reserva después de este se rigen por 8.6 y 8.8;
deben conservar todo el historial.

``` text
RESPALDO A
      ↓
   Carlos
```

impide asignar ese respaldo simultáneamente a Pedro, María u otra
persona.

🔵 V3 deberá representar una identidad persistente del
antecedente/respaldo que permita garantizar esta regla, sin equiparar
un identificador interno nuevo con un antecedente administrativo
externo diferente.

## 8.5 El respaldo no es divisible

🟢 El respaldo no se modela como una bolsa de días reutilizable.

Ejemplo:

``` text
Respaldo:
01 ───────────────── 30

Carlos:
01 ─────── 20
```

Los días 21--30 **no quedan disponibles para otra persona**.

No se modelará:

``` text
saldo = 10 días
```

ni un mecanismo de consumos parciales reutilizables.

## 8.6 Cambio de persona

🟢 **RF-06 — Antes del documento:** Gestión de Personas puede cambiar
la persona propuesta, manteniendo comprometido el respaldo de origen.
Debe comprobar la disponibilidad de la nueva persona, liberar la reserva
anterior, registrar la nueva y conservar el historial del cambio. Todo
ocurre atómicamente: si falla la nueva reserva, permanece la anterior.

🟢 Una vez generado el documento, no se sustituye a la persona editando
silenciosamente la solicitud ni la versión documental existente.
**RF-07:** una anulación formal posterior puede liberar su reserva,
manteniendo comprometido el respaldo y conservando íntegro el documento.
Esto no termina automáticamente una contratación ya formalizada.
**RF-08:** solo Gestión de Personas ejecuta esa anulación formal; la
jefatura puede solicitarla. Se registran solicitante, ejecutor, motivo
y fecha. El procedimiento para modificar o terminar un contrato ya
formalizado continúa pendiente (18).

Si posteriormente Pedro debe cubrir un nuevo período:

``` text
SOLICITUD A
→ Carlos
→ Documento Carlos
```

no se transforma en:

``` text
SOLICITUD A
→ Pedro   ❌
```

Después del documento, Pedro requiere una nueva solicitud y un respaldo
válido propio; no puede consumir días sobrantes del respaldo A todavía
comprometido:

``` text
SOLICITUD B
→ Pedro
→ Documento Pedro
```

La antigua regla «nueva persona = nueva solicitud/respaldo
administrativo» queda **reemplazada para el período anterior al
documento** por RF-06. Después del documento sigue prohibida la
sustitución silenciosa; una rectificación documental se rige por 10.11.

## 8.7 Identidad, compromiso y liberación del respaldo

🟢 **RF-10 — Identificación:** cada respaldo transitorio tiene un
identificador interno obligatorio y generado automáticamente, una
referencia administrativa externa opcional cuando exista, funcionario
origen, motivo, fechas desde/hasta e historial de modificaciones,
afectaciones y liberaciones. El identificador interno no prueba que dos
registros correspondan a antecedentes administrativos distintos. No se
presupone una referencia externa ni un formato institucional universal.

🟢 **RF-01 — Compromiso:** el respaldo se compromete exclusivamente con
una solicitud **al aprobar Gestión de Personas los antecedentes**. Crear,
guardar, enviar o iniciar revisión no lo compromete. Aprobación y
compromiso son una operación transaccional.

🟢 **RF-02 — Devolución posterior:** si una solicitud aprobada se
devuelve para corrección, el respaldo continúa comprometido. La
devolución no libera automáticamente reservas (véase 9.5).

🟢 **RF-03 — Anulación:** si la solicitud se anula antes de generar el
documento institucional, se libera el respaldo comprometido. Si el
documento ya se generó, el respaldo continúa comprometido aun cuando se
anule la solicitud. Ambos resultados conservan historial.

🟢 **RF-04 — Reutilización:** el respaldo liberado válidamente por una
anulación anterior al documento puede usarse en una solicitud nueva,
tras verificar que no haya compromisos vigentes ni conflictos de
períodos. Esto no habilita a reutilizar días sobrantes de un respaldo
todavía comprometido (8.5).

## 8.8 Reserva de persona propuesta

🟢 **RF-05 — Reserva:** al aprobar antecedentes, Gestión de Personas
reserva las fechas de la persona propuesta, cuando existe, junto con el
compromiso del respaldo. Si aún no existe persona, solo se compromete el
respaldo. Una incorporación posterior exige comprobar disponibilidad
antes de crear la reserva. Los cambios y liberaciones se rigen por 8.6.

🟢 La reserva debe respetar la cobertura contenida en el respaldo y la
ausencia de solapamientos de 8.1 y 8.3. Propuesta, reserva y persona
finalmente contratada son hechos distintos.

## 8.9 Rectificación de fechas

🟢 **RF-12 — Fechas del respaldo:** un respaldo libre puede corregirse.
Si está comprometido, la rectificación requiere intervención controlada
de Gestión de Personas, historial de valores anteriores y nuevos,
responsable, motivo, validación de conflictos y coherencia con la
cobertura.

🟢 **RF-14 — Cobertura contenida:** si las nuevas fechas del respaldo
dejan la cobertura fuera de su período, se bloquea la operación hasta
que Gestión de Personas ajuste explícitamente ambos períodos. No se
ajustan fechas contractuales automáticamente. Si ya existe documento,
se aplica además 10.11.

------------------------------------------------------------------------

# 9. Workflow, estados y bandejas

## 9.1 Motor transversal

Se conserva el concepto de motor transversal de estados y transiciones.

``` text
ESTADO ACTUAL
      +
ACCIÓN
      +
PERMISOS
      +
ALCANCE
      +
REQUISITOS
      ↓
TRANSICIÓN
      ↓
HISTORIAL
```

🟢 Los cambios de estado no deben dispersarse arbitrariamente por
controladores.

Cada tipo de trámite puede definir su workflow específico.

## 9.2 Estado ≠ acción

Acciones como guardar, revisar, adjuntar, generar documento o registrar
una observación no tienen por qué equivaler automáticamente a un estado
nuevo.

## 9.3 Borrador

🟢 Un borrador puede estar incompleto.

🟢 Guardar no equivale a enviar.

🟢 Un borrador no reserva períodos ni respaldos administrativos.

## 9.4 Envío

Enviar implica comprobar los requisitos mínimos correspondientes al tipo
de solicitud.

🟢 Una solicitud transitoria sin reemplazante también puede enviarse.

La existencia de candidato no puede ser una condición transversal de
envío.

**Implementación Fase 3C.1B:** las solicitudes de Reemplazo con fila
`solicitudes_contrato` son V3; las históricas sin esa fila conservan las
reglas V2. El formulario V3 registra el respaldo transitorio en borrador y
permite enviar con o sin candidato. El envío exige respaldo apto, contexto
organizacional y períodos válidos, registra historial y no crea afectación
ni reserva. El inicio de revisión vuelve a comprobar esos antecedentes.

## 9.5 Revisión y devolución

La revisión corresponde a una etapa de trabajo de Gestión de Personas
según permisos y alcance.

Una devolución debe conservar:

``` text
quién
cuándo
motivo
estado anterior
estado posterior
```

🟢 Las correcciones posteriores no eliminan devoluciones anteriores.

🟢 Pueden existir múltiples ciclos de devolución/corrección utilizando
el historial, sin columnas específicas para cada devolución.

🟢 **RF-16 — Corrección antes de aprobación:** cuando se devuelve la
solicitud, la jefatura o el administrativo autorizado puede corregir el
respaldo; Gestión de Personas puede corregirlo directamente durante su
revisión. Cada operación depende de estado, permisos y alcance.

🟢 **RF-17 — Devolución después de aprobación:** la jefatura o el
administrativo autorizado solo puede modificar los antecedentes que
Gestión de Personas habilite expresamente. Cambiar el respaldo, sus
fechas, la persona reservada o su período exige intervención de Gestión
de Personas. El respaldo y las reservas vigentes permanecen
comprometidos salvo una operación institucional autorizada (8.6–8.9).
El backend debe aplicar esta protección incluso si la interfaz ofrece
un campo editable.

🟢 **RF-18 — Habilitación mixta:** Gestión de Personas puede habilitar
secciones completas o campos específicos de una sección para una
devolución concreta. Se registra qué se habilitó, quién lo hizo y para
qué devolución. Integridad, permisos y reservas prevalecen sobre esa
habilitación.

El flujo V2 actualmente solo devuelve desde `EN_REVISION`, antes de
aprobar. La devolución posterior a aprobación es una capacidad V3 por
diseñar; no se debe interpretar el estado V2
`DEVUELTA_PARA_CORRECCION` como liberación automática.

## 9.6 Aprobación ≠ formalización

La conformidad o aprobación de antecedentes no equivale automáticamente
a formalizar el resultado contractual.

**Implementación Fase 3C.1B:** Gestión de Personas aprueba la solicitud
transitoria V3 desde la ruta autorizada mediante una operación atómica que
compromete el respaldo, reserva a la persona propuesta si existe, guarda la
revisión y el historial, y llega a `LISTA_GENERAR_DOCUMENTO`. La
generación documental V3 sigue bloqueada en interfaz y backend; el flujo
documental V2 histórico conserva su comportamiento. Las operaciones de
anulación, liberación, rectificación y devolución posterior a aprobación
siguen pendientes.

## 9.7 Documento generado ≠ proceso terminado

Generar un documento no significa necesariamente que el trámite esté
finalizado.

Pueden existir actuaciones posteriores:

``` text
Documento generado
        ↓
Formalización
        ↓
Gestión de Personas
        ↓
DocDigital / evidencia posterior
```

## 9.8 Bandejas

Una bandeja es una **vista de trabajo**, no un estado.

Ejemplos:

``` text
Requieren mi atención
Nuevas
En revisión
Esperando corrección
Pendientes de documento
Pendientes de formalización
Finalizadas
```

"Requiere mi atención" debe determinarse por las acciones que realmente
puede ejecutar el usuario.

## 9.9 Stepper e historial visual

🟢 El stepper debe reflejar el camino real del trámite.

Una etapa opcional como "Devuelta para corrección" no debe aparecer como
completada si nunca ocurrió.

🔵 Puede utilizarse un stepper para el camino principal y una línea de
tiempo separada para eventos opcionales/repetibles.

## 9.10 Anulación y matriz resumida de efectos

🟢 **RF-09 — Responsable:** antes del primer envío, la jefatura
autorizada puede anular directamente su solicitud. Después del envío,
solo Gestión de Personas puede ejecutar la anulación; la jefatura puede
solicitarla. Se respetan permiso, alcance organizacional y estado. La
anulación de la solicitud se distingue de la anulación formal de una
reserva de persona posterior al documento (RF-07/RF-08, 8.6).

La matriz resume efectos funcionales V3; los nombres de estados y las
transiciones técnicas adicionales quedan por diseñar. «Reserva» se
refiere a una persona propuesta existente.

| Momento / actuación | Respaldo transitorio | Reserva de persona | Responsable de ejecución |
|---|---|---|---|
| Borrador, guardado, envío o revisión inicial | Libre; sin compromiso | Ninguna | Jefatura o administrativo autorizado registra/envía; Gestión de Personas revisa. |
| Aprobación de antecedentes | Comprometido con una solicitud, atómicamente | Se crea si hay persona; si no, ninguna | Gestión de Personas. |
| Devolución posterior a aprobación | Permanece comprometido | Permanece vigente; edición protegida | Gestión de Personas devuelve y habilita correcciones. |
| Anulación de solicitud antes del primer envío | Libre | Ninguna | Jefatura autorizada. |
| Anulación de solicitud después del envío y antes del documento | Se libera si estaba comprometido | Se libera la reserva vigente si existía | Gestión de Personas; la jefatura puede solicitar. |
| Cambio de persona antes del documento | Permanece comprometido | Sustitución atómica tras validar disponibilidad | Gestión de Personas. |
| Documento generado | Permanece comprometido | Permanece vigente | Generación sujeta a autoridad documental pendiente. |
| Anulación de solicitud después del documento | Permanece comprometido | Puede liberarse mediante anulación formal separada | Gestión de Personas; la jefatura puede solicitar. |
| Rectificación posterior al documento que afecta su contenido | Permanece comprometido | Se revalida si cambian fechas; no autoriza sustituir persona | Gestión de Personas; nueva versión documental, procedimiento pendiente. |

La formalización no libera el respaldo (12.3). La liberación de una
reserva posterior al documento no extingue por sí sola un contrato
formalizado (8.6 y 11).

------------------------------------------------------------------------

# 10. Documentos, plantillas y snapshots

## 10.1 Adjunto ≠ documento generado

**Adjunto:** archivo incorporado por un usuario como antecedente.

**Documento generado:** salida producida por la plataforma utilizando
información estructurada y una plantilla.

🟢 Son conceptos distintos.

## 10.2 Documento generado ≠ formalización

Un PDF generado puede existir antes de que el resultado administrativo
esté formalizado.

``` text
GENERADO ≠ FORMALIZADO
```

## 10.3 Plantillas versionadas

Una plantilla debe ser versionable.

``` text
Solicitud Transitoria
│
├── v1
├── v2
└── v3
```

🟢 Cambiar una plantilla no modifica documentos históricos.

El documento generado debe registrar qué versión de plantilla utilizó.

## 10.4 Con y sin reemplazante

🟢 La presencia o ausencia de reemplazante no obliga por sí sola a crear
tipos documentales distintos.

Una misma plantilla conceptual puede incluir una sección condicional:

``` text
SOLICITUD TRANSITORIA

Funcionario origen
Motivo
Período
Justificación

[Si existe]
Reemplazante propuesto
Datos del reemplazante
```

## 10.5 Autoridad documental ≠ usuario generador

🟢 La autoridad representada en el documento y el usuario que
técnicamente lo genera son conceptos independientes.

El sistema debe poder conservar simultáneamente:

``` text
Autoridad / remitente institucional:
Subdirector

Generado por:
Usuario real que ejecutó la acción
```

🟢 La autoridad documental se resuelve automáticamente utilizando el
contexto organizacional, las responsabilidades vigentes, la fecha y las
reglas aplicables a ese acto documental. No puede ser atribuida
manualmente por el usuario operador.

🟢 La autoridad de la solicitud no implica necesariamente que sea la
autoridad de todos los documentos posteriores. Cada acto documental puede
resolver su propia autoridad.

🟢 La autoridad puede corresponder a una Persona sin cuenta User.

🟡 La autoridad exacta y la precedencia titular/subrogante para cada tipo
documental permanecen por confirmar.

## 10.6 Snapshot documental

🟢 Al generar un documento debe congelarse la información administrativa
utilizada para producir esa versión.

Conceptualmente puede incluir:

``` text
SNAPSHOT
│
├── personas
├── RUT
├── unidades
├── origen
├── destino
├── solicitante
├── autoridad
├── profesión
├── estamento
├── grado
├── períodos
├── condiciones
└── fecha
```

Si posteriormente cambia la jefatura, unidad, grado u otro dato maestro,
el documento histórico no cambia.

🟢 La autoridad resuelta para una versión documental forma parte de su
snapshot. Si posteriormente cambia el jefe, subdirector, titular,
subrogante o la estructura organizacional, esa versión no recalcula su
autoridad.

## 10.7 Momento del snapshot

🟢 El snapshot no debe congelarse prematuramente al crear el borrador.

``` text
DATOS VIVOS
     ↓
REVISIÓN
     ↓
DECISIÓN
     ↓
GENERACIÓN DOCUMENTAL
     ↓
SNAPSHOT
```

Cada documento representa el momento administrativo correspondiente.

🔵 Funcionalmente, cada documento/version puede poseer su propio
snapshot.

## 10.8 Versiones y regeneración

Si un documento necesita corregirse:

``` text
Documento v1
      ↓
corrección
      ↓
Documento v2
```

No se sobrescribe silenciosamente v1.

🟢 Las versiones anteriores permanecen históricas.

🟢 Debe poder identificarse cuál es la versión vigente.

🟢 La regeneración debe respetar permisos, alcance, estado y reglas
documentales.

Dependiendo de la etapa, podrá requerirse un motivo de regeneración.

## 10.9 Hash e integridad

🟢 Se conserva el uso de hash SHA-256 para integridad técnica de los
archivos.

El hash no equivale a firma electrónica ni certificación jurídica.

## 10.10 Anulación lógica

🟢 Los documentos que hayan participado del proceso no deben desaparecer
silenciosamente.

Si un archivo es incorrecto, debe poder anularse conservando, cuando
corresponda:

``` text
motivo
usuario
fecha
versión
```

## 10.11 Rectificación posterior a la generación

🟢 **RF-13 — Versiones rectificadas:** cuando una rectificación
posterior al documento afecta su contenido, Gestión de Personas genera
una nueva versión rectificada y conserva íntegra la original. Cada
versión conserva fecha, responsable y datos correspondientes, además
de una relación trazable con la anterior. No se sobrescriben archivos,
snapshots ni reservas históricas. Esta regla de versionado no autoriza
por sí sola a sustituir la persona tras el documento (8.6).

⚪ El versionado documental completo corresponde a Fase 5. Hasta que
exista el flujo autorizado, esta regla no habilita la regeneración del
PDF V3. Continúa el bloqueo descrito en 10.5 y en el README.

------------------------------------------------------------------------

# 11. Formalización

Formalizar no significa solamente cambiar un estado.

Debe existir un resultado administrativo identificable.

Conceptualmente:

``` text
FORMALIZACIÓN
│
├── Persona finalmente contratada
├── Unidad destino
├── Cargo / función
├── Estamento
├── Profesión
├── Grado
├── Calidad contractual
├── Fecha inicio
├── Fecha término
├── Evidencia correspondiente
└── Actor / fecha de registro
```

según corresponda al proceso.

🟢 La formalización conserva explícitamente las condiciones finales.

🟢 Propuesta y condiciones finales pueden diferir.

🟢 La formalización debe ser idempotente: ejecutarla accidentalmente dos
veces no puede duplicar sus efectos.

🟢 Formalizar un contrato no significa necesariamente crear una nueva
destinación funcional.

## 11.1 Correcciones posteriores

🟢 Una vez generado el documento, los datos que sustentan esa versión no
se modifican silenciosamente.

🟢 Si un documento ya formalizado contiene un error, **Gestión de
Personas es responsable de gestionar la corrección**, dado que esa
unidad continuará el proceso documental hacia DocDigital.

La corrección debe conservar trazabilidad y no convertir
retroactivamente una versión histórica en otra.

La jefatura, secretaria o solicitante no debe volver atrás y modificar
silenciosamente un trámite ya formalizado.

🟡 El nombre y procedimiento institucional exacto para rectificación,
anulación u otra actuación posterior debe confirmarse.

------------------------------------------------------------------------

# 12. Integridad, concurrencia y reglas críticas

## 12.1 Concurrencia

🟢 Toda acción crítica debe volver a comprobar el estado real al
ejecutarse.

Ejemplo:

``` text
Ana abre trámite
Pedro abre trámite

Ana → aprueba

Pedro → intenta devolver
```

La acción de Pedro debe validar nuevamente estado, permisos, alcance y
reglas antes de ejecutarse.

## 12.2 Duplicidad y borradores

Pueden existir borradores incompletos o similares mientras no hayan
adquirido efectos administrativos.

🟢 Los borradores no reservan respaldos ni períodos.

Al avanzar a una etapa efectiva deben aplicarse las reglas de
solapamiento, exclusividad y consistencia.

## 12.3 Exclusividad después de finalizar

🟢 La exclusividad de un respaldo no desaparece porque el trámite llegue
a documento generado o formalización.

Una formalización no "libera" el antecedente utilizado.

## 12.4 Consistencia antes de formalizar

🟢 La formalización debe comprobar que los datos y documentos vigentes
corresponden al resultado que se pretende formalizar.

No basta con comprobar que "existe un PDF".

## 12.5 Backend como autoridad

Aunque un botón esté deshabilitado en la interfaz, cada operación debe
comprobar en backend:

``` text
permiso
+
alcance
+
estado
+
integridad
+
reglas de negocio
```

🟢 Restringir visualmente un selector de unidad o persona no constituye
autorización. El backend debe validar cada contexto organizacional de
forma independiente antes de aceptar la operación.

## 12.6 Bloqueo ≠ advertencia

Las reglas confirmadas que representan una contradicción deben bloquear
la operación.

Las situaciones informativas o todavía no definidas institucionalmente
pueden generar advertencias cuando corresponda.

## 12.7 Protección persistente

🟢 Las invariantes críticas deben protegerse en más de una capa cuando
técnicamente sea posible.

La arquitectura determinará posteriormente qué reglas requieren
constraints, índices únicos, transacciones, locks, servicios de dominio
u otros mecanismos.

## 12.8 Impacto técnico de respaldos y reservas 🔵

El diseño de Fase 3 debe resolver, sin convertir estas opciones en reglas
funcionales nuevas:

-   La identidad persistente del respaldo, su exclusividad y la
    referencia externa opcional, sin deducir identidad administrativa
    desde un ID generado (RF-10 y 18).
-   Un historial inmutable de afectaciones, liberaciones, cambios de
    persona y reutilización posterior de un respaldo liberado; la
    consulta actual puede cambiar, pero no deben reescribirse eventos
    pasados (8.7, 8.8 y 13).
-   La representación de reservas de fechas de personas propuestas y su
    liberación, separada de la persona finalmente contratada y de la
    cobertura contractual formalizada (8.3, 8.8 y 11).
-   La atomicidad de aprobación/compromiso, cambio de persona y
    anulación; en MySQL 8.4, definir una estrategia de transacciones y
    bloqueos que serialice la competencia por funcionario origen,
    respaldo y persona, incluso cuando todavía no hay fila de reserva.
    Una consulta de solapamiento sin protección de concurrencia no basta.
-   La validación de intervalos inclusivos: dos períodos se solapan si
    `inicio_a <= fin_b` y `inicio_b <= fin_a`; períodos consecutivos sin
    día común son válidos. Revalidar al registrar, aprobar, reservar,
    cambiar, reutilizar o rectificar, según corresponda.
-   La habilitación de secciones o campos por devolución concreta, con
    autorización y validación backend de cada modificación (RF-17 y
    RF-18). Una habilitación no suspende invariantes.
-   Operaciones distintas para anulación de solicitud, anulación formal
    de reserva y rectificación documental; cada una requiere efectos,
    responsable e historial propios (8.6, 9.10 y 10.11).
-   Compatibilidad con trámites V2 históricos: `solicitudes_contrato`
    identifica el contexto V3, mientras los V2 carecen de esa fila. No
    se inventan respaldos, reservas, autoridades ni equivalencias
    contractuales mediante backfill.

El esquema `solicitudes_contrato` de Fase 2 conserva modalidad,
solicitante, autoridad, origen y destino; por sí solo no representa la
identidad, compromiso, liberación ni historial del respaldo. El flujo
V2 actual exige reemplazante al enviar, valida superposición del origen
incluso en borrador, aprueba sin reservar persona y no ofrece anulación
ni devolución posterior a aprobación. Son brechas de implementación
respecto de V3, no excepciones a estas reglas funcionales.

------------------------------------------------------------------------

# 13. Historial y trazabilidad

El historial debe permitir reconstruir:

``` text
qué ocurrió
quién actuó
cuándo
sobre qué
por qué
```

No todo evento tiene que cambiar el estado.

Pueden existir eventos como:

``` text
Adjunto agregado
Documento generado
Documento regenerado
Documento anulado
Observación registrada
Formalización registrada
Respaldo comprometido o liberado
Reserva de persona creada, cambiada o liberada
Corrección habilitada para una devolución
```

sin que todos impliquen una transición principal.

🟢 El actor registrado en auditoría es siempre el usuario real que
ejecutó la acción, incluso cuando actuó representando a una autoridad
institucional.

🟢 Personas, unidades, documentos y trámites históricos no deben
desaparecer por desactivación.

🟢 Los cambios posteriores de nombre, unidad, autoridad u otros datos
actuales no deben modificar snapshots documentales históricos.

🟢 Las modificaciones y rectificaciones conservan valores anteriores y
nuevos, responsable, fecha y motivo cuando corresponda. La reutilización
de un respaldo liberado enlaza su compromiso anterior con el nuevo sin
sobrescribir afectaciones, reservas ni documentos históricos.

------------------------------------------------------------------------

# 14. Sistemas externos

## 14.1 DocDigital

DocDigital continúa siendo externo.

La plataforma puede registrar, cuando corresponda:

``` text
fecha de envío
referencia / identificador
usuario que registró
estado conocido
documento final
observaciones
```

pero no replicará el workflow interno de firma o tramitación de
DocDigital.

🟢 Gestión de Personas será quien continúe el tratamiento del documento
hacia DocDigital en el proceso definido.

⚪ Una integración automática podrá evaluarse posteriormente.

## 14.2 SIRH

SIRH continúa siendo sistema externo/oficial donde corresponda.

La plataforma puede eventualmente almacenar o importar antecedentes
provenientes de SIRH indicando su fuente.

``` text
Origen:
SIRH
```

No debe duplicar innecesariamente funcionalidades que ya existen allí.

🟢 La ausencia de información contractual en Gestión RRHH no permite
concluir automáticamente que existió trabajo impago, deuda u otra
situación administrativa.

------------------------------------------------------------------------

# 15. Casos funcionales de referencia

## 15.1 Transitoria con origen distinto del destino

``` text
Secretaria de Subdirección Administrativa
        │
        │ registra
        ▼
SOLICITUD DE CONTRATO TRANSITORIA

Registrador:
Secretaria

Unidad solicitante:
Subdirección Administrativa

Autoridad institucional:
Subdirector Administrativo vigente
(resuelto automáticamente)

Funcionario origen:
Juan Pérez

Unidad origen:
Abastecimiento

Período origen:
01–30 octubre

Reemplazante propuesto:
Carlos Ferreira

Destino funcional:
Informática

Estamento:
Profesional

Grado:
16

Período contractual:
01–20 octubre
```

El ejemplo es válido siempre que Abastecimiento e Informática estén
dentro del alcance operativo de quien confecciona la solicitud. Que la
Subdirección Administrativa sea una unidad solicitante válida no autoriza
por sí sola un origen o destino fuera de ese alcance.

El sistema debe comprobar, entre otras reglas:

``` text
✓ origen sin solapamiento
✓ período contractual contenido
✓ Carlos sin contrato transitorio superpuesto
✓ respaldo no asignado a otra persona
✓ destino válido
✓ requisitos documentales correspondientes
```

Gestión de Personas revisa, determina condiciones finales, genera
documento con snapshot, formaliza y continúa la gestión documental.

Carlos puede continuar funcionalmente en Informática aunque
posteriormente tenga otro contrato consecutivo respaldado por otra
solicitud.

## 15.2 Transitoria sin reemplazante

``` text
Funcionario origen:
Juan Pérez

Período:
01–30 octubre

Reemplazante propuesto:
NINGUNO
```

La solicitud puede enviarse y llegar a Gestión de Personas.

La falta de candidato no constituye por sí sola un error de completitud.

## 15.3 Contratos consecutivos

``` text
Respaldo A
Juan → Carlos 01–20 octubre

Respaldo B
Pedro → Carlos 21–31 octubre
```

✓ Permitido.

``` text
Respaldo A
Juan → Carlos 01–20 octubre

Respaldo B
Pedro → Carlos 15–31 octubre
```

❌ Bloqueado por solapamiento contractual de Carlos.

## 15.4 Uso parcial del respaldo

``` text
Respaldo A:
01–30 octubre

Carlos:
01–20 octubre
```

Los días 21--30 no constituyen un saldo reutilizable para otra persona.

## 15.5 Cambio posterior de persona

``` text
SOLICITUD A
→ Carlos
→ Documento generado
```

Si posteriormente se necesita a Pedro, no se modifica la solicitud A.

Debe existir:

``` text
SOLICITUD B
→ Pedro
→ Documento Pedro
```

## 15.6 Registrador distinto de autoridad

``` text
Secretaria de Subdirección Administrativa
        │
        │ registra
        ▼
Solicitud
        │
        ├── Registrador: Secretaria
        ├── Unidad solicitante: Subdirección Administrativa
        └── Autoridad institucional:
            Subdirector Administrativo vigente
            (resuelto automáticamente)
```

Historial:

``` text
Registrado por: Secretaria
```

Documento:

``` text
Solicitante / autoridad:
Subdirector correspondiente
```

El documento puede representar institucionalmente al Subdirector,
mientras la auditoría conserva que la acción fue realizada por la
Secretaria. No se falsea que el Subdirector operó el sistema.

------------------------------------------------------------------------

# 16. Invariantes V3 confirmadas 🟢

Estas reglas deben considerarse obligatorias para el diseño técnico y
posteriormente convertirse en pruebas automatizadas:

1.  Persona ≠ Usuario.
2.  Registrador ≠ solicitante/autoridad institucional.
3.  Unidad origen ≠ unidad destino.
4.  Destinación funcional ≠ cobertura contractual.
5.  Persona propuesta ≠ necesariamente persona finalmente contratada.
6.  Adjunto ≠ documento generado.
7.  Documento generado ≠ formalización.
8.  Una solicitud transitoria puede enviarse sin candidato.
9.  El período contractual debe estar contenido dentro del período
    origen.
10. No pueden registrarse respaldos transitorios superpuestos del mismo
    funcionario origen, aunque tengan motivos distintos.
11. No puede existir solapamiento temporal de reservas vigentes de una
    persona propuesta ni de contratos transitorios de una misma persona.
12. Una persona puede tener contratos transitorios consecutivos, pero no
    simultáneos.
13. Un respaldo transitorio comprometido se asocia a una sola solicitud
    y cubre como máximo a una persona a la vez.
14. Un respaldo no puede dividirse entre varias personas.
15. El uso parcial de un respaldo no genera saldo reutilizable.
16. Antes del documento, Gestión de Personas puede cambiar atómicamente
    la persona reservada; después, no se sustituye silenciosamente y una
    nueva cobertura requiere solicitud y respaldo válidos propios.
17. Una vez generado el documento, la persona y los datos de esa versión
    no se sustituyen mediante edición silenciosa.
18. Los documentos históricos conservan snapshot y versión.
19. Regenerar un documento crea una nueva versión; no sobrescribe
    silenciosamente la anterior.
20. Las correcciones posteriores a formalización corresponden a Gestión
    de Personas.
21. La formalización debe ser idempotente y no duplicar efectos.
22. Formalizar no implica necesariamente crear una nueva destinación
    funcional.
23. Un borrador no reserva respaldos ni períodos.
24. Las reglas críticas se vuelven a validar en backend al ejecutar la
    acción.
25. La exclusividad del respaldo persiste después de generar/formalizar.
26. Los trámites administrativos históricos no se eliminan físicamente.
27. Desactivar personas o unidades no destruye relaciones históricas.
28. La autoridad documental no se obtiene automáticamente del usuario
    que genera el archivo.
29. DocDigital y SIRH permanecen como sistemas externos.
30. El sistema no infiere deuda, trabajo impago u otros hechos
    administrativos a partir de vacíos de información.
31. La unidad solicitante se selecciona únicamente dentro del acceso
    operativo vigente del usuario.
32. Unidad solicitante, unidad origen y unidad destino son contextos
    independientes y se validan por separado.
33. La autoridad institucional se resuelve automáticamente; el operador
    no puede atribuirla mediante el formulario.
34. La autoridad institucional puede ser una Persona sin cuenta User.
35. Titular y subrogante participan en la resolución de autoridades, pero
    el subrogante no sustituye universalmente al titular para todo acto.
36. La auditoría conserva al usuario real aunque el acto represente a una
    autoridad institucional distinta.
37. La autoridad de cada versión documental queda congelada en su
    snapshot histórico.
38. La autoridad de la solicitud no determina necesariamente la autoridad
    de todos los documentos posteriores.
39. Los usuarios operativos de una Subdirección actúan dentro de su ámbito
    organizacional, sin crear roles específicos por unidad.
40. Alcance global y permiso global son conceptos distintos;
    `tramites.ver_todos` no es un bypass universal.
41. El filtrado de selectores no reemplaza la revalidación de alcance en
    backend.
42. Solo la aprobación de antecedentes compromete el respaldo y, si
    existe persona propuesta, reserva sus fechas en la misma operación.
43. Una devolución posterior a aprobación mantiene respaldo y reservas;
    las ediciones se limitan a lo habilitado por Gestión de Personas.
44. La anulación previa al documento libera el respaldo comprometido;
    después del documento este permanece comprometido.
45. Un respaldo liberado válidamente puede reutilizarse tras verificar
    compromisos y períodos; nunca se reutiliza un saldo de días de un
    respaldo comprometido.
46. Las anulaciones y rectificaciones conservan historial, y un
    documento rectificado crea una nueva versión íntegra.

------------------------------------------------------------------------

# 17. Decisiones de diseño pendientes 🔵

La arquitectura V3 deberá proponer, sin alterar las reglas funcionales
anteriores:

-   Cómo representar una identidad persistente del antecedente/respaldo
    transitorio, sus compromisos, liberaciones y reutilizaciones.
-   Cómo separar físicamente destinación funcional y cobertura
    contractual.
-   Cómo representar técnicamente la unidad solicitante, el registrador,
    la autoridad institucional resuelta, el origen y el destino,
    preservando sus significados independientes.
-   Cómo modelar propuesta y resultado final.
-   Cómo almacenar snapshots por documento/version.
-   Cómo determinar la versión documental vigente.
-   Cómo proteger técnicamente solapamientos y exclusividad bajo
    concurrencia en MySQL 8.4, también cuando aún no hay reserva.
-   Cómo representar reservas de persona propuesta y liberaciones sin
    sobrescribir su historial.
-   Cómo representar anulaciones de solicitud, anulaciones formales de
    reserva y rectificaciones documentales como actuaciones distintas.
-   Cómo persistir habilitaciones de edición por devolución, sección o
    campo, y aplicarlas en backend.
-   Cómo diseñar bandejas sin convertirlas innecesariamente en estados.
-   Cómo reutilizar antecedentes documentales personales en una fase
    futura manteniendo evidencia de la versión utilizada.

Estas decisiones deben surgir del análisis técnico V2 → V3 y no
improvisarse durante la implementación.

------------------------------------------------------------------------

# 18. Pendientes institucionales 🟡

Los siguientes puntos requieren confirmación antes de convertirse en
reglas rígidas:

-   Requisitos y workflow exactos de contratación permanente.
-   Catálogo definitivo de motivos/situaciones transitorias.
-   Requisitos documentales exactos por tipo de solicitud.
-   Significado institucional exacto de dotación, destinación y vínculo.
-   Catálogo y reglas de calidad contractual.
-   Autoridad institucional correspondiente a cada acto documental; la
    generación V3 continúa bloqueada hasta definirla.
-   Reglas específicas de titular/subrogante para cada acto.
-   Criterio institucional para verificar si referencias externas
    iguales, ausentes o diferentes corresponden al mismo antecedente
    administrativo. El identificador interno no resuelve esta identidad.
-   Procedimiento para confirmar a la persona finalmente contratada,
    distinto de la propuesta y su reserva.
-   Incompatibilidades contractuales adicionales a la superposición de
    fechas.
-   Equivalencia contractual verificable de registros V2 históricos;
    no se inferirá de un vínculo de dotación.
-   Terminología formal para extensiones, continuidades o nuevos
    períodos.
-   Procedimiento institucional exacto para modificar o terminar
    contratos ya formalizados; la liberación de una reserva no lo hace.
-   Alcance y condiciones para rectificar una solicitud ya anulada o un
    documento generado que no llegó a formalización. Afecta la
    autorización y las transiciones posteriores, sin alterar la regla
    de conservación de versiones.
-   Evidencia final requerida para considerar una contratación
    formalizada.
-   Información proveniente de SIRH que deba conservarse localmente.

Estos pendientes no invalidan el núcleo V3. La implementación no debe
inventar respuestas para ellos.

------------------------------------------------------------------------

# 19. Evoluciones futuras ⚪

## 19.1 Espacios de Unidad

Cada unidad organizacional podrá disponer en el futuro de un espacio de
trabajo propio aprovechando la jerarquía y los accesos existentes.

``` text
UNIDAD
│
├── Resumen
├── Dotación
├── Trámites
├── Documentos internos
├── Planificación
└── Tareas
```

El espacio documental de una unidad es diferente del expediente de un
trámite.

Su implementación es posterior al núcleo de Gestión RRHH y no debe
ampliar el alcance de las fases actuales.

## 19.2 Otras evoluciones

Quedan fuera del alcance inmediato:

-   Integración automática con DocDigital.
-   Integraciones/importaciones SIRH.
-   Reutilización avanzada de antecedentes personales.
-   Notificaciones externas.
-   Planificación y tareas de unidad.
-   Espacios documentales colaborativos.
-   Automatizaciones que dependan de reglas institucionales todavía no
    confirmadas.

------------------------------------------------------------------------

# 20. Frontera funcional V3

``` text
                 PLATAFORMA INTERNA DE GESTIÓN
                            │
          ┌─────────────────┼──────────────────┐
          │                 │                  │
     ORGANIZACIÓN        PERSONAS          SEGURIDAD
          │                 │                  │
          └────────────┬────┴──────────┬───────┘
                       │               │
                    TRÁMITES        DOTACIÓN
                       │               │
                ┌──────┴──────┐        │
                │             │        │
           SOLICITUDES    EXPEDIENTE   │
                │             │        │
        ┌───────┴──────┐      │        │
        │              │      │        │
   PERMANENTE     TRANSITORIA │        │
                       │      │        │
                  ORIGEN      │        │
                       │      │        │
                  PROPUESTA   │        │
                       │      │        │
                GESTIÓN PERSONAS       │
                       │      │        │
                  DOCUMENTO ──┘        │
                       │               │
                 FORMALIZACIÓN         │
                       │               │
                ┌──────┴──────┐        │
                │             │        │
             COBERTURA    DESTINACIÓN ─┘
             CONTRACTUAL   FUNCIONAL
                │
                ↓
          SISTEMAS EXTERNOS
          SIRH / DocDigital
```

------------------------------------------------------------------------

# 21. Criterio para evolución V2 → V3

V3 **no debe abordarse como una reescritura automática desde cero**.

La siguiente etapa debe comparar la implementación V2 real contra este
mapa funcional para determinar:

``` text
V2 ACTUAL
    ↓
MAPA FUNCIONAL V3
    ↓
BRECHAS
    ↓
ARQUITECTURA V3
    ↓
PLAN DE EVOLUCIÓN V2 → V3
    ↓
FASES PEQUEÑAS
    ↓
IMPLEMENTACIÓN + PRUEBAS
```

Se espera reutilizar, cuando el análisis técnico confirme que siguen
siendo adecuados, conceptos ya existentes como:

-   núcleo transversal de trámites;
-   personas y usuarios;
-   estructura organizacional;
-   responsabilidades y accesos;
-   permisos;
-   motor de estados/transiciones;
-   historial;
-   expediente privado;
-   versionamiento e integridad documental;
-   plantillas y documentos generados;
-   snapshots;
-   formalización transaccional;
-   navegación contextual;
-   bandejas transversales.

Las áreas que requieren especial revisión son:

``` text
UNIDAD SOLICITANTE ≠ ORIGEN ≠ DESTINO

REGISTRADOR ≠ AUTORIDAD / SOLICITANTE

ALCANCE GLOBAL ≠ PERMISO GLOBAL

PROPUESTA ≠ RESULTADO FINAL

DESTINACIÓN FUNCIONAL ≠ COBERTURA CONTRACTUAL

RESPALDO TRANSITORIO = EXCLUSIVO Y NO DIVISIBLE

DOCUMENTO GENERADO = PUNTO DE CONGELAMIENTO
```

## 21.1 Relación de las decisiones consolidadas con las fases

🔵 **Fase 3 — respaldos y reservas:** analizar y diseñar la identidad
del respaldo, compromisos y liberaciones, reutilización autorizada,
reservas de personas, solapamientos inclusivos, operaciones atómicas y
correcciones habilitadas. La matriz de 9.10 y el análisis de 12.8 son
sus entradas funcionales; este documento no implementa el flujo.

🟡 **Fase 4 — identidad definitiva:** distinguir la persona propuesta y
reservada de la persona finalmente contratada. Su procedimiento de
confirmación sigue pendiente; no se infiere desde la propuesta ni desde
un vínculo de dotación V2.

⚪ **Fase 5 — versionado documental:** preservar cada documento y
snapshot, generar nuevas versiones rectificadas cuando cambie su
contenido y mantener la relación entre versiones. La generación V3
sigue bloqueada hasta definir la autoridad institucional del acto.

⚪ **Fase 6 — formalización y destino funcional:** diferenciar cobertura
contractual final, destinación funcional y reservas previas. La
anulación formal de una reserva no constituye término de un contrato
formalizado; el procedimiento institucional correspondiente permanece
pendiente.

------------------------------------------------------------------------

# 22. Instrucción para el primer análisis técnico con Codex

Una vez incorporado este archivo al repositorio, el primer trabajo sobre
V3 debe ser **de análisis y diseño, no de implementación**.

Codex deberá:

1.  Leer este `MAPA_FUNCIONAL_V3.md`, el README vigente, migraciones,
    modelos, servicios, policies, rutas y tests.
2.  Comparar la implementación V2 actual contra este mapa funcional.
3.  Identificar componentes reutilizables, componentes que requieren
    adaptación y acoplamientos incompatibles con V3.
4.  Proponer un modelo técnico/arquitectura de evolución V2 → V3.
5.  Identificar migraciones de datos necesarias y riesgos de
    compatibilidad.
6.  Proponer fases pequeñas, ordenadas y comprobables.
7.  Asociar a cada fase pruebas de regresión y nuevas pruebas
    funcionales.
8.  No modificar archivos ni implementar código durante este primer
    análisis.
9.  No inventar reglas para los puntos marcados 🟡.
10. Señalar explícitamente cualquier ambigüedad que impida diseñar una
    parte de la solución.

------------------------------------------------------------------------

# 23. Criterio de aprobación

Este mapa se considera la **fuente funcional V3** hasta que una decisión
institucional o funcional posterior sea incorporada explícitamente.

Antes de implementar una fase, su diseño debe ser compatible con las
invariantes confirmadas de este documento.

Si una nueva regla institucional contradice este mapa, primero debe
actualizarse la definición funcional y luego evaluarse su impacto
técnico.

------------------------------------------------------------------------

**Fin - Mapa Funcional V3**
