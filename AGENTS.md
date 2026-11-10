# AGENTS.md - Sistema de Gestión de Trámites y Documentos de RRHH

## Contexto
Proyecto interno del Hospital de Illapel para gestionar trámites administrativos de RRHH. El sistema es multiproceso y parte con Reemplazos y Horas Extraordinarias.

## Antes de modificar código
1. Lee `README.md` como documento maestro y consulta las fuentes institucionales de `docs/` solo cuando el alcance lo requiera.
2. Ejecuta `git status`, `git branch --show-current`, `git log -1 --oneline` y `git remote -v`.
3. Confirma que estás trabajando desde Ubuntu Linux nativo o WSL2 con Ubuntu, nunca dentro de la distribución `docker-desktop`.
4. No sobrescribas cambios existentes que no hayas creado.
5. Trabaja solo en el alcance solicitado de la entrega actual.

## Entorno
- Todo el proyecto se ejecuta en Docker Compose.
- No instalar PHP, Composer, Node, MySQL ni Nginx directamente en Windows.
- Los comandos de PHP/Composer/Artisan se ejecutan dentro del contenedor de aplicación.
- Los comandos npm se ejecutan dentro del contenedor Node definido por el proyecto.
- Timezone de aplicación: `America/Santiago`.

## Arquitectura
- Monolito modular Laravel.
- `tramites` es la raíz de procesos.
- Datos específicos en tablas/modelos por tipo de trámite.
- Controladores pequeños.
- Validación en Form Requests.
- Autorización en Policies/Gates.
- Reglas/casos de uso en `app/Actions` o `app/Services`.
- No introducir Repository Pattern, CQRS, DDD pesado ni microservicios sin requerimiento explícito.

## Base de datos
- MySQL 8, utf8mb4.
- FKs e índices explícitos.
- No usar MySQL ENUM para estados de trámites.
- Catálogos se desactivan en vez de eliminarse cuando hayan sido usados.
- RUT único y normalizado.
- Horas extraordinarias se almacenan en minutos enteros.
- No guardar archivos como BLOB.

## Estados e historial
- Ningún controlador debe asignar `estado_tramite_id` directamente.
- Todas las transiciones pasan por un servicio/Action central que:
  1. valida transición,
  2. valida permiso,
  3. valida condición/observación,
  4. cambia estado dentro de transacción,
  5. registra `tramite_historial`.
- El historial es inmutable desde la aplicación.

## Archivos
- Storage privado, nunca `public/storage` para documentos de RRHH.
- Nombre físico aleatorio/ULID/UUID.
- Guardar nombre original, MIME, tamaño, SHA-256, usuario, fecha y versión.
- Descarga solo mediante controlador autorizado.
- Una corrección crea nueva versión; no borrar la anterior.
- Planilla SIRH: PDF. No OCR ni extracción automática en el MVP.

## Seguridad
- Autorización en backend para cada lectura/escritura sensible.
- No confiar en campos `user_id`, `unidad_id`, rol o estado enviados por el cliente sin validar.
- CSRF activo.
- No registrar contenido sensible en logs.
- Datos de fixtures/tests siempre ficticios.

## Código y estilo
- PSR-12 / Laravel Pint.
- Nombres de clases y métodos descriptivos en inglés o español consistente; preferencia: dominio en español si coincide con la BD/documentación (`Tramite`, `Unidad`, `Persona`).
- Códigos técnicos de estado/acción en MAYÚSCULAS_CON_GUION_BAJO para estados y `namespace.accion` para permisos.
- Evitar lógica de negocio en Blade.
- Evitar consultas N+1; usar eager loading en bandejas/detalles.
- No optimizar prematuramente ni crear abstracciones para procesos aún no definidos.

## Pruebas mínimas por cambio
- Permisos/Policies afectados.
- Transiciones válidas e inválidas.
- Reglas críticas de la fase.
- Archivos privados/versionado cuando aplique.
- `php artisan test` para suite relevante.
- `./vendor/bin/pint --test` antes de cerrar fase.

## Pendientes que NO debes inventar
- Grados E.U.S. oficiales.
- Documentos obligatorios por tipo de reemplazo.
- Plantilla definitiva de Reemplazo.
- Correcciones ortográficas de catálogos entregados.
- Integraciones automáticas con SIRH o DocDigital.
- Firma electrónica interna.

## Entrega al terminar una entrega
Reporta:
- qué implementaste,
- archivos principales cambiados,
- migraciones/seeders creados,
- pruebas/comandos ejecutados y resultado,
- decisiones tomadas,
- pendientes o bloqueos,
- `git status` final.
No avances a un alcance nuevo sin una nueva instrucción.

## Base metodológica NEXO y adaptación local
La base metodológica proviene de **NEXO v0.3**, documentada en
`~/desarrollo/laboratorio-ia/AGENTS.md` y sus tres perfiles de `agentes/`.
Esta integración adapta esas reglas a Gestión RRHH; no constituye una especificación
formal de NEXO v0.4. README.md continúa siendo el documento maestro del proyecto.
Este AGENTS.md centraliza las reglas comunes y técnicas; los perfiles
[Arquitecto](agentes/arquitecto.md), [Desarrollador](agentes/desarrollador.md) y
[Tester](agentes/tester.md) añaden responsabilidades sin ampliar permisos.

Las adaptaciones locales son: arquitectura y seguridad de RRHH, entorno Ubuntu
nativo o WSL2, separación de revisión documental y pruebas de aplicación, y
transferencias en la conversación mientras no exista seguimiento local autorizado.
No crear ni enlazar fichas o procedimientos de seguimiento inexistentes.

El [procedimiento de seguimiento](docs/seguimiento/README.md) y la
[plantilla](docs/seguimiento/plantilla.md) organizan la continuidad mediante una
única ficha canónica por trabajo `NEXO-RRHH-AAAA-NNN`. Su existencia no autoriza
crear fichas: cada creación o actualización requiere permiso para ese archivo.
Sin ese permiso, mantener las transferencias en conversación. Contrastar siempre
la memoria documental con las autorizaciones vigentes y el estado real de Git.
Los permisos de migraciones se distinguen de análisis, implementación, pruebas,
commit y push; ninguno se concede por implicación. Todas las reglas de E1 anteriores
y siguientes permanecen vigentes.

### Reglas comunes
- Responder y documentar en español, conservando nombres técnicos.
- Comprender el requerimiento, arquitectura y convenciones antes de actuar.
- Distinguir requisitos confirmados, supuestos y decisiones pendientes; no convertir
  supuestos en requisitos aprobados.
- Explicar cambios y decisiones, priorizar soluciones simples y dependencias mínimas.
- Identificar riesgos, regresiones y mitigaciones antes de implementar.
- Identificar la base Git y los cambios preexistentes; no sobrescribir trabajo ajeno.
- Definir criterios observables, método de verificación y evidencia; no declarar
  éxito sin evidencia ni afirmar funcionamiento a partir de una revisión documental.

### Autorizaciones separadas y seguridad metodológica
El responsable humano mantiene el control del alcance y de la aceptación.
- Distinguir autorización de análisis, implementación, ejecución de pruebas,
  commit y push. Ninguna de ellas concede las demás por implicación.
- Analizar o aprobar un plan no autoriza a modificar archivos. Implementar requiere
  autorización humana explícita de fase, archivos y acciones.
- Ejecutar pruebas o verificaciones con efectos requiere permiso vigente para
  esas acciones y entorno; evaluar antes escrituras, cachés, bases y servicios.
- Instalar herramientas o dependencias, crear commits y realizar push requieren
  autorización explícita para cada acción.
- No acceder a producción, ejecutar comandos destructivos ni modificar configuraciones
  críticas del sistema operativo sin autorización explícita. No eliminar ni
  reinicializar bases como verificación ordinaria.
- No exponer credenciales ni datos sensibles en respuestas, logs o evidencias.
  No enviar datos institucionales o personales a servicios externos sin autorización
  y controles adecuados.
- Una recomendación, transferencia, cambio de perfil o silencio no concede permisos.
  Los perfiles son funciones: no crean agentes, ejecutores ni delegaciones automáticas.
- Ante permiso ambiguo, solicitar aclaración y continuar solo trabajo independiente
  ya autorizado. Ante impedimento del sandbox, detener la operación y solicitar
  autorización específica; no reutilizar permisos excepcionales de sesiones anteriores.
- No ampliar alcance ni comenzar otra etapa por haber completado la anterior.

Registrar en la conversación las autorizaciones sin inventarlas: identificador
único, mensaje humano y fecha disponible, plan y versión, fase, archivos, acciones,
verificaciones y entorno, correcciones incluidas y exclusiones. Si no está claro
si una corrección está cubierta, consultar. Registrar cambios o revocaciones y
detener lo afectado. Guardar este registro en un archivo exige permiso de escritura.

### Proporcionalidad y evidencia
Clasificar y justificar por alcance, incertidumbre y riesgo; usar el nivel mayor:
- Pequeña: acotada, conocida, reversible y de bajo riesgo; ficha breve en conversación.
- Mediana: integración conocida o varios componentes con riesgo moderado; plan por
  fases y resultados por criterio.
- Compleja: incertidumbre alta, arquitectura transversal, tratamiento de datos
  sensibles o efectos difíciles de revertir; alternativas, mitigaciones y evidencia detallada.

Siempre informar alcance, autorización, criterios, verificación, limitaciones y
siguiente responsable. Agrupar apartados es válido; «no aplica» requiere motivo.
La proporcionalidad no reduce controles. Si crece el alcance o cambia el diseño
aprobado, volver a planificación y aprobación humana.

Cada criterio tendrá identificador, condición observable, método y resultado:
cumplido, incumplido o no verificado, con evidencia o motivo. «No aplica» no
sustituye un criterio aplicable que no pudo comprobarse.

### Verificación documental y pruebas de aplicación
Las reglas de pruebas mínimas anteriores se conservan para cambios de aplicación
cuando correspondan y estén autorizadas. Una fase exclusivamente documental se
verifica mediante inspección de contenido, coherencia, enlaces, alcance y diff;
no requiere ejecutar Laravel, Pint, compilación, Docker, migraciones ni seeders.
Esa revisión no acredita el funcionamiento de la aplicación. Si una prueba
pertinente está prohibida o no autorizada, registrar la limitación y no ejecutarla.

### Coordinación, transferencia y aceptación
Al iniciar o retomar, contrastar la solicitud, plan, permisos, entrega previa y
estado real de Git. Consultar seguimiento solo si existe y está dentro del alcance;
crearlo o actualizarlo exige autorización para esos archivos.
Flujo metodológico: propuesta → pendiente de aprobación → autorizada →
en implementación → en verificación → pendiente de aceptación → aceptada.
Una condición bloqueante debe indicar causa y decisión necesaria.

Cada transferencia incluirá:
1. Trabajo, fase, estado y perfil que entrega.
2. Plan y autorización humana aplicables.
3. Commit base, rama, estado Git y diff o evidencia equivalente; incluir cambios
   sin commit y archivos sin seguimiento, distinguiendo los preexistentes.
4. Resultados por criterio, comandos, evidencia y limitaciones.
5. Riesgos, pendientes, siguiente responsable y acción esperada.

No crear commits para identificar una entrega. Si cambia el material revisado,
reevaluar las conclusiones afectadas. Los defectos vuelven a desarrollo solo si
su corrección está autorizada; cambios de alcance o diseño vuelven a planificación.
El Tester emite recomendación favorable, favorable con observaciones o desfavorable;
solo el responsable humano acepta. Detenerse al terminar la fase y esperar
aceptación y nueva autorización antes de avanzar.

Responsabilidad documental dentro del alcance autorizado:
- Arquitecto: plan, alternativas y decisiones justificadas.
- Desarrollador: cambios implementados, uso y operación afectados, pruebas mantenidas.
- Tester: cobertura, hallazgos y evidencia independiente.
