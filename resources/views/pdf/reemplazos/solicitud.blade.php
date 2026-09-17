<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12.7mm; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; line-height: 1.28; }
        .header { width: 100%; margin: 0 0 3mm; border-collapse: collapse; }
        .header td { padding: 0; vertical-align: top; }
        .header-left { width: 55%; white-space: nowrap; }
        .header-right { width: 45%; text-align: right; }
        .logo-minsal { width: 27.8mm; vertical-align: top; }
        .logo-trabajando { width: 28.7mm; margin-left: 1.5mm; vertical-align: top; }
        .logo-hospital { width: 27.8mm; vertical-align: top; }
        .date { margin: 0 0 3mm; text-align: right; }
        h1 { margin: 0 0 5mm; text-align: center; font-size: 11pt; font-weight: 700; text-decoration: underline; }
        .routing { width: 100%; margin: 0 0 5mm; border-collapse: collapse; }
        .routing th { width: 10mm; padding: 0 2mm 1.2mm 0; text-align: left; vertical-align: top; }
        .routing td { padding: 0 0 1.2mm; vertical-align: top; }
        .item { margin: 0 0 3.2mm; text-align: justify; page-break-inside: auto; }
        .review-title { margin: 0 0 2mm; font-weight: 700; }
        .review-line { margin: 0 0 1.2mm; }
        .classifications { margin: 1.5mm 0 2.5mm 8mm; }
        .classification { margin: 0 0 .8mm; }
        .mark { display: inline-block; width: 14mm; font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
@php
    $asset = fn (string $path) => preg_replace('/\s+/', '', file_get_contents(resource_path('assets/reemplazos/'.$path)));
    $generatedAt = \Carbon\CarbonImmutable::parse($snapshot['generado_at']);
    $selectedClassification = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($snapshot['revision']['clasificacion_area']));
    $classifications = [
        'Áreas críticas',
        'Áreas Semi-Críticas',
        'Áreas de apoyo Asistencial',
        'Área de Apoyo Administrativo y no crítico',
    ];
@endphp

<table class="header">
    <tr>
        <td class="header-left">
            <img class="logo-minsal" src="data:image/png;base64,{{ $asset('logo-minsal.png.b64') }}">
            <img class="logo-trabajando" src="data:image/png;base64,{{ $asset('logo-trabajando.png.b64') }}">
        </td>
        <td class="header-right">
            <img class="logo-hospital" src="data:image/jpeg;base64,{{ $asset('logo-hospital.jpeg.b64') }}">
        </td>
    </tr>
</table>

<p class="date">Illapel, {{ $generatedAt->format('d/m/Y') }}</p>

<h1>SOLICITUD DE REEMPLAZO DE PERSONAL</h1>

<table class="routing">
    <tr><th>A:</th><td>{{ $snapshot['destinatario'] }}</td></tr>
    <tr><th>DE:</th><td>{{ mb_strtoupper($snapshot['tramite']['creador'] ?? 'JEFATURA SOLICITANTE') }} · {{ mb_strtoupper($snapshot['tramite']['unidad']) }}</td></tr>
</table>

<p class="item"><strong>1.- Tipo de reemplazo: {{ $snapshot['solicitud']['tipo'] }}</strong><br>Agradeceré a Ud., autorizar contrato de reemplazo durante el período comprendido entre el {{ \Carbon\CarbonImmutable::parse($snapshot['funcionario']['desde'])->format('d/m/Y') }} y el {{ \Carbon\CarbonImmutable::parse($snapshot['funcionario']['hasta'])->format('d/m/Y') }}, correspondiente a {{ $snapshot['funcionario']['nombre'] }}, RUT N.º {{ \App\Support\Rut\Rut::format($snapshot['funcionario']['rut']) }}.@if($snapshot['funcionario']['estamento']) Estamento: {{ $snapshot['funcionario']['estamento'] }}.@endif @if($snapshot['funcionario']['profesion']) Profesión: {{ $snapshot['funcionario']['profesion'] }}.@endif @if($snapshot['funcionario']['cargo']) Cargo o función: {{ $snapshot['funcionario']['cargo'] }}.@endif @if($snapshot['funcionario']['calidad'] ?? null) Calidad: {{ $snapshot['funcionario']['calidad'] }}.@endif</p>

<p class="item"><strong>2.-</strong> El reemplazo se justifica por: {{ $snapshot['solicitud']['justificacion'] }}</p>

<p class="item"><strong>3.-</strong> Se propone como reemplazante a: {{ $snapshot['reemplazante']['nombre'] }}, RUT N.º {{ \App\Support\Rut\Rut::format($snapshot['reemplazante']['rut']) }}, desde el {{ \Carbon\CarbonImmutable::parse($snapshot['reemplazante']['desde'])->format('d/m/Y') }} hasta el {{ \Carbon\CarbonImmutable::parse($snapshot['reemplazante']['hasta'])->format('d/m/Y') }}. Quien desempeñará funciones en la Unidad/Servicio: {{ $snapshot['tramite']['unidad'] }}.@if(($snapshot['schema_version'] ?? 1) >= 2) Estamento: {{ $snapshot['reemplazante']['estamento'] }}.@if($snapshot['reemplazante']['profesion']) Profesión: {{ $snapshot['reemplazante']['profesion'] }}.@endif Calidad contractual: {{ $snapshot['reemplazante']['calidad_contractual'] }}. Cargo o función: {{ $snapshot['reemplazante']['cargo_funcion'] }}.@endif</p>

<div class="item">
    <p class="review-title">4.- Revisión de Gestión de Personas:</p>
    <p class="review-line">Último Grado del Escalafón: <strong>{{ $snapshot['revision']['grado_eus'] }}° E.U.S.</strong></p>
    <p class="review-line">El reemplazo pertenece a:</p>
    <div class="classifications">
        @foreach($classifications as $classification)
            @php($isSelected = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($classification)) === $selectedClassification)
            <p class="classification"><span class="mark">{{ $isSelected ? '__X__' : '_____' }}</span>{{ $classification }}</p>
        @endforeach
    </div>
    <p class="review-line">Cumple con normativa vigente: <strong>SÍ: {{ $snapshot['revision']['cumple_normativa'] ? '__X__' : '_____' }} &nbsp;&nbsp;/&nbsp;&nbsp; NO: {{ $snapshot['revision']['cumple_normativa'] ? '_____' : '__X__' }}</strong></p>
</div>
</body>
</html>
