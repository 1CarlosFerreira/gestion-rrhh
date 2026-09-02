<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
@page { margin: 18mm 18mm 16mm; } body { font-family: DejaVu Sans, sans-serif; color:#111; font-size:10.5pt; line-height:1.35; }
.brand { width:100%; border-collapse:collapse; margin-bottom:4mm; } .brand td { vertical-align:middle; } .brand-left { width:30%; } .brand-center { text-align:center; width:40%; } .brand-right { text-align:right; width:30%; } .logo-hospital { width:34mm; } .logo-minsal { width:25mm; } .logo-trabajando { width:29mm; }
h1 { text-align:center; font-size:14pt; margin:2mm 0 6mm; text-decoration:underline; } .memo { text-align:right; font-weight:bold; margin-bottom:5mm; }
.routing { width:100%; border-collapse:collapse; margin-bottom:6mm; } .routing td { padding:1.5mm 0; vertical-align:top; } .label { width:12mm; font-weight:bold; }
.section { margin:0 0 5mm; page-break-inside:avoid; } .section-title { font-weight:bold; margin-bottom:1.5mm; } .value { text-align:justify; white-space:pre-line; }
.review { width:100%; border-collapse:collapse; page-break-inside:avoid; } .review td { padding:1mm 0; } .options { margin-top:1mm; line-height:1.65; }
.footer { position:fixed; bottom:-8mm; left:0; right:0; text-align:center; font-size:7.5pt; color:#666; }
</style></head><body>
@php
    $r = $tramite->reemplazo; $revision = $tramite->revisionReemplazo; $remitente = $r->remitente_snapshot ?? [];
    $funcionario = $r->funcionario_snapshot ?? []; $reemplazante = $r->reemplazante_snapshot ?? [];
    $tipo = trim($r->tipoReemplazo->nombre); $esVacante = str_contains(mb_strtolower($tipo), 'vacante');
    $motivo = $esVacante ? 'Cargo vacante'.(filled($reemplazante['cargo'] ?? null) ? ': '.$reemplazante['cargo'].'.' : '.') : $tipo.' de '.($funcionario['nombre_completo'] ?? 'funcionario no individualizado').(filled($funcionario['cargo'] ?? null) ? ', '.$funcionario['cargo'] : '').'.';
    $roles = collect($remitente['roles'] ?? [])->reject(fn($rol) => $rol === 'Administrador')->implode(', ');
    $cargoRemitente = $remitente['cargo'] ?? ($roles ?: 'Jefatura solicitante');
    $rut = \App\Support\Rut\Rut::format($reemplazante['rut'] ?? '');
    $areas = ['Área crítica','Área semi-crítica','Área de apoyo asistencial','Área de apoyo administrativo y no crítico'];
    $asset = fn(string $path) => preg_replace('/\s+/', '', file_get_contents(resource_path('assets/reemplazos/'.$path)));
@endphp
<table class="brand"><tr><td class="brand-left"><img class="logo-hospital" src="data:image/jpeg;base64,{{ $asset('logo-hospital.jpeg.b64') }}"></td><td class="brand-center"><img class="logo-trabajando" src="data:image/png;base64,{{ $asset('logo-trabajando.png.b64') }}"></td><td class="brand-right"><img class="logo-minsal" src="data:image/png;base64,{{ $asset('logo-minsal.png.b64') }}"></td></tr></table>
<div class="memo">MEMORANDUM N.º __________ /{{ $generadoAt->year }}</div>
<h1>SOLICITUD DE REEMPLAZO DE PERSONAL</h1>
<table class="routing"><tr><td class="label">A</td><td>: {{ $destinatario }}</td></tr><tr><td class="label">DE</td><td>: {{ mb_strtoupper($remitente['nombre'] ?? '') }}<br>{{ mb_strtoupper($cargoRemitente) }}@if(filled($remitente['unidad'] ?? null)) · {{ mb_strtoupper($remitente['unidad']) }}@endif</td></tr></table>
<div class="section"><div class="section-title">1.- Agradeceré a Ud., autorizar contrato de reemplazo motivado por:</div><div class="value">{{ $motivo }} Periodo de ausencia del funcionario: {{ $r->ausencia?->fecha_inicio?->format('d/m/Y') }} al {{ $r->ausencia?->fecha_termino?->format('d/m/Y') }}.</div></div>
<div class="section"><div class="section-title">2.- El reemplazo se justifica por:</div><div class="value">{{ $r->justificacion }}</div></div>
<div class="section"><div class="section-title">3.- Se propone como reemplazante a:</div><div class="value">{{ $reemplazante['nombre_completo'] ?? '' }}, RUT N.º {{ $rut }}. Estamento: {{ $reemplazante['estamento'] ?? '' }}. Profesión: {{ $reemplazante['profesion'] ?? '' }}. Cargo o función: {{ $reemplazante['cargo'] ?? '' }}. Periodo efectivo del reemplazo: {{ $r->fecha_inicio?->format('d/m/Y') }} al {{ $r->fecha_termino?->format('d/m/Y') }}. Unidad/servicio: {{ $reemplazante['unidad'] ?? $tramite->unidadServicio->nombre }}.</div></div>
<div class="section"><div class="section-title">4.- Revisión de Gestión de Personas:</div><table class="review"><tr><td>Último Grado del Escalafón <strong>{{ $revision->grado_eus_informado }}° E.U.S.</strong></td></tr><tr><td>El reemplazo pertenece a:</td></tr><tr><td class="options">@foreach($areas as $area) [{{ $revision?->clasificacionArea?->nombre === $area ? 'X' : ' ' }}] {{ $area }}@if(!$loop->last)<br>@endif @endforeach</td></tr><tr><td>Cumple con normativa vigente: [{{ $revision?->cumple_normativa === true ? 'X' : ' ' }}] Sí &nbsp;&nbsp;&nbsp; [{{ $revision?->cumple_normativa === false ? 'X' : ' ' }}] No</td></tr></table></div>
<div class="footer">Hospital de Illapel · Documento generado para validación administrativa y posterior tramitación en DocDigital</div>
</body></html>
