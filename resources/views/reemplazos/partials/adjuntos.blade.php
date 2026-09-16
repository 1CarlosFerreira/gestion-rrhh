@php($adjuntosVisibles = $tramite->adjuntos->whereNotIn('id', $excluirAdjuntoIds ?? []))
<section aria-labelledby="adjuntos-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex items-center justify-between gap-3">
        <h2 id="adjuntos-title" class="text-base font-semibold text-gray-950">Documentos</h2>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $adjuntosVisibles->count() }}</span>
    </div>
    <div class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200">
        @forelse($adjuntosVisibles as $adjunto)
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900">{{ $adjunto->original_name }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $adjunto->tipoDocumento?->nombre ?? 'Sin tipo documental' }} · versión {{ $adjunto->version }}</p>
                </div>
                @can('tramites.adjuntos.descargar')
                    <a href="{{ route('reemplazos.adjuntos.download', [$tramite, $adjunto]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">Descargar</a>
                @endcan
            </div>
        @empty
            <p class="px-4 py-5 text-sm text-gray-500">Sin documentos adjuntos.</p>
        @endforelse
    </div>
</section>
