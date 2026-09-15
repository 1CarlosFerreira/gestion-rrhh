@php($revision = $tramite->revisionReemplazo)
<section aria-labelledby="revision-lectura-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 id="revision-lectura-title" class="text-base font-semibold text-gray-950">Revisión Gestión de Personas</h2>
    <dl class="mt-4 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-xs text-gray-500">Grado E.U.S.</dt><dd class="mt-1 font-medium text-gray-900">{{ $revision?->grado_eus ?? 'No informado' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Clasificación de área</dt><dd class="mt-1 font-medium text-gray-900">{{ $revision?->clasificacionArea?->nombre ?? 'No informada' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Cumple normativa</dt><dd class="mt-1 font-medium text-gray-900">{{ $revision?->cumple_normativa === null ? 'No informado' : ($revision->cumple_normativa ? 'Sí' : 'No') }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Observación administrativa</dt><dd class="mt-1 whitespace-pre-line text-gray-800">{{ $revision?->observacion_administrativa ?: 'Sin observación.' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Revisado por</dt><dd class="mt-1 font-medium text-gray-900">{{ $revision?->revisadoPor?->name ?? 'No informado' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Fecha de revisión</dt><dd class="mt-1 font-medium text-gray-900">{{ $revision?->revisado_at?->format('d/m/Y H:i') ?? 'No informada' }}</dd></div>
    </dl>
</section>
