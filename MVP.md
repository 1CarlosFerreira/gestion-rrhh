# MVP - Sistema de Gestión de Trámites y Documentos de RRHH

## 1. Objetivo
Construir una plataforma interna multiproceso para RRHH. El núcleo es **Trámite** y los primeros procesos son **Solicitud de Reemplazo** y **Horas Extraordinarias**.

Fuentes funcionales obligatorias de referencia:
- `Documento_Maestro_Gestion_Tramites_RRHH_v0.5.docx`
- `Modelo_Datos_Conceptual_Gestion_Tramites_RRHH_v1.1.docx`
- `Especificacion_Tecnica_Gestion_Tramites_RRHH_v1.0.docx`
- `Modelo_Logico_BD_Gestion_Tramites_RRHH_v1.0.docx`

Si una decisión no está respaldada por esas fuentes o por este archivo, no la inventes: deja un TODO documentado y detente si bloquea la fase.

## 2. Stack cerrado
- Laravel 12
- PHP 8.3 FPM
- MySQL 8
- Nginx
- Docker Compose sobre WSL2
- Blade + Tailwind CSS + Vite
- Alpine.js solo para interacciones puntuales
- Laravel Breeze (Blade)
- spatie/laravel-permission
- Queue `database`
- Storage privado local Laravel
- PHPUnit para pruebas críticas
- Laravel Pint
- Git
- Timezone: `America/Santiago`

**Todo PHP/Composer/Node/MySQL/Nginx se ejecuta en Docker. No instalar dependencias del proyecto en Windows.**

## 3. Principios no negociables
1. `tramites` es la tabla raíz física del sistema.
2. Reemplazo y Horas Extra usan tablas de detalle relacionadas con `tramites`.
3. Adjuntos, historial, documentos y DocDigital se implementan una sola vez y se reutilizan.
4. Estados/transiciones son tablas configurables por tipo de trámite; no usar MySQL ENUM para estados.
5. Cambios de estado solo mediante un servicio/acción central y siempre generan historial.
6. Persona es única por RUT normalizado.
7. Catálogos se desactivan; no se eliminan registros ya usados.
8. Archivos son privados, con hash SHA-256 y versiones; no se guardan como BLOB.
9. SIRH es externo: no modificar asistencia y no parsear/OCR el PDF en el MVP.
10. DocDigital es externo: no implementar firmas ni visaciones internas.
11. No inventar grados E.U.S., documentos obligatorios ni plantilla final de Reemplazo.
12. Mantener controladores pequeños; reglas en Actions/Services, autorización en Policies y validación en Form Requests.

## 4. Roles base
- `Jefe de Servicio`
- `Gestión de Personas`
- `Administrador`

DocDigital se controla por permisos granulares, no por una persona fija.

## 5. Trámite: Reemplazo
### Jefatura
- Selecciona Unidad/Servicio.
- Selecciona Tipo de reemplazo.
- Busca funcionario por RUT/nombre y reutiliza datos disponibles.
- Justificación libre y obligatoria al enviar.
- Busca/registra reemplazante.
- Estamento/profesión se autocompletan si existen o se seleccionan.
- Fecha inicio/término.
- La unidad del reemplazante es la misma del trámite.
- Adjunta respaldos. Requisitos obligatorios: pendientes de RRHH.

### Gestión de Personas
- Grado E.U.S. (catálogo pendiente de valores oficiales).
- Clasificación de área.
- Cumple normativa Sí/No.
- Puede devolver con observación obligatoria.
- La Jefatura puede corregir toda su parte y reenviar.

### Estados
`BORRADOR -> ENVIADA_GESTION_PERSONAS -> EN_REVISION -> DEVUELTA_CORRECCION -> ENVIADA_GESTION_PERSONAS`

o desde `EN_REVISION -> LISTA_GENERAR_DOCUMENTO -> DOCUMENTO_GENERADO -> ENVIADA_DOCDIGITAL -> FORMALIZADA`.

## 6. Trámite: Horas Extraordinarias
- La Jefatura selecciona Unidad, mes calendario y uno o más funcionarios.
- Gestión de Personas obtiene el PDF de SIRH y lo carga por funcionario.
- La Jefatura revisa con el funcionario; el funcionario no tiene usuario.
- Resultado: `CONFORME` u `OBSERVADA`; observación obligatoria si hay discrepancia.
- Si está observada, Personal corrige primero en SIRH y carga una nueva versión del PDF.
- Si está conforme a la primera, se omite corrección.
- Las horas finales se capturan manualmente en **minutos** desde SIRH; no OCR.
- El Informe Técnico se genera desde datos estructurados.
- Firma/V°B° se resuelve posteriormente en DocDigital.

### Estados
`BORRADOR -> ENVIADA_GESTION_PERSONAS -> PLANILLA_DISPONIBLE -> EN_REVISION_JEFATURA`

Luego `OBSERVADA -> EN_CORRECCION_SIRH -> PLANILLA_DISPONIBLE` o `CONFORME -> INFORME_TECNICO_GENERADO -> ENVIADA_DOCDIGITAL -> FORMALIZADA`.

## 7. Pendientes externos (no bloquear fases anteriores)
- Plantilla definitiva de Reemplazo.
- Matriz de documentos obligatorios por tipo de Reemplazo.
- Valores oficiales de grados E.U.S.
- Validación ortográfica/institucional de catálogos.
- Confirmación final de la plantilla de Informe Técnico.
- Reglas definitivas de numeración/identificador de DocDigital.

## 8. Plan por fases
### Fase 0 - Infraestructura
Laravel + Docker + Nginx + MySQL + Vite. No tablas de negocio.

**Aceptación:** `docker compose up -d` levanta servicios; `http://localhost` responde 200; `php artisan migrate` funciona dentro del contenedor; README documenta comandos.

### Fase 1 - Seguridad y catálogos
Breeze, Spatie RBAC, unidades, estamentos, profesiones, tipos de trámite, estados/transiciones, tipos de reemplazo y clasificación de área.

### Fase 2 - Personas y dotación
Personas, RUT, vínculos persona-unidad, búsqueda/autocompletado.

### Fase 3 - Núcleo de trámites
`tramites`, motor de estados, historial, listado/bandejas y detalle común.

### Fase 4 - Expediente
Adjuntos privados, tipos documentales, SHA-256, descarga autorizada y versionado.

### Fase 5 - Reemplazo
Flujo completo hasta `LISTA_GENERAR_DOCUMENTO` sin inventar plantilla final.

### Fase 6 - Horas Extraordinarias
Solicitud, participantes, planillas SIRH, revisión/corrección, captura de horas y conformidad.

### Fase 7 - Documentos
Plantillas/versiones, snapshots y generación cuando estén disponibles los formatos finales.

### Fase 8 - DocDigital
Registro de envío/formalización y documento final.

### Fase 9 - Cierre MVP
Dashboard, notificaciones internas, hardening, pruebas UAT y documentación.

## 9. Flujo de trabajo con Codex
Para cada fase:
1. Leer `AGENTS.md`, `MVP.md` y documentación relacionada.
2. Verificar WSL, rama, último commit, remoto y `git status`.
3. Si existen cambios ajenos/no esperados, detenerse antes de sobrescribirlos.
4. Implementar **solo una fase**.
5. Ejecutar migraciones/pruebas/Pint correspondientes.
6. Entregar resumen de archivos cambiados, pruebas ejecutadas, pendientes y riesgos.
7. Esperar revisión manual.
8. Hacer commit solo cuando se indique o cuando la instrucción de la fase lo solicite explícitamente.
