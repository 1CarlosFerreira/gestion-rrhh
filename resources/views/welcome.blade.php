<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 text-slate-900">
    <main class="mx-6 max-w-2xl rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">Hospital de Illapel</p>
        <h1 class="mt-3 text-3xl font-bold">Sistema de Gestión de Trámites y Documentos de RRHH</h1>
        <p class="mt-4 text-slate-600">Infraestructura base operativa.</p>
    </main>
</body>
</html>
