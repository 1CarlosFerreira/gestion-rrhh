<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-800">Inicio</h2></x-slot>

    @php($indicators = [
        ['Nuevas por revisar', $newForReview, 'nuevas_por_revisar', 'bg-blue-100 text-blue-800'],
        ['En revisión', $inReview, 'en_revision_gp', 'bg-indigo-100 text-indigo-800'],
        ['Devueltas esperando corrección', $returned, 'devueltas_esperando_correccion', 'bg-amber-100 text-amber-900'],
        ['Listas para generar documento', $readyToGenerate, 'listas_generar_documento', 'bg-emerald-100 text-emerald-800'],
    ])

    <div class="py-10"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section><h1 class="text-2xl font-semibold text-slate-900">Bienvenido/a, {{ auth()->user()->name }}</h1><p class="mt-1 text-slate-600">Revisa las solicitudes recibidas y continúa las gestiones pendientes.</p></section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumen de solicitudes recibidas">
            @foreach($indicators as [$label, $value, $filter, $accent])
                <a href="{{ route('gestion-personas.bandeja', ['estado_grupo' => $filter]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $accent }}">{{ $label }}</span><p class="mt-3 text-3xl font-semibold text-slate-900">{{ $value }}</p></a>
            @endforeach
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><div><h2 class="text-lg font-semibold text-slate-900">Requieren mi atención</h2><p class="mt-1 text-sm text-slate-600">Solicitudes en las que puedes realizar la siguiente acción del flujo.</p></div><div class="mt-4 space-y-3">
            @forelse($attention as $tramite)
                @php($state = $tramite->estadoTramite->codigo)
                @php($wasReturned = $tramite->historial->contains('action_code', 'DEVOLVER_CORRECCION'))
                @php($reviewer = $tramite->historial->where('action_code', 'INICIAR_REVISION')->last()?->usuario)
                <article class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 p-4"><div class="min-w-0"><p class="font-medium text-slate-900">{{ $tramite->codigo }} · {{ $tramite->tipoTramite->nombre }}</p><p class="mt-1 text-sm text-slate-600">{{ $tramite->unidadServicio->nombre }} · Creada por {{ $tramite->creador?->name ?? 'Usuario no disponible' }}</p><p class="mt-1 text-sm text-slate-500">{{ $tramite->submitted_at ? 'Recibida '.$tramite->submitted_at->format('d-m-Y H:i') : 'Actualizada '.$tramite->updated_at->format('d-m-Y H:i') }}</p>@if($state === 'EN_REVISION' && $reviewer)<p class="mt-1 text-sm text-indigo-700">En revisión por {{ $reviewer->name }}</p>@endif</div><div class="flex shrink-0 flex-wrap items-center gap-3"><x-status-badge :estado="$tramite->estadoTramite" />@if($state === 'ENVIADA_GESTION_PERSONAS')<form method="POST" action="{{ route('reemplazos.review.start', $tramite) }}">@csrf<button type="submit" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">{{ $wasReturned ? 'Revisar corrección' : 'Iniciar revisión' }}</button></form>@elseif($state === 'EN_REVISION')<a href="{{ route('reemplazos.review.show', $tramite) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Continuar revisión</a>@else<a href="{{ route('tramites.show', $tramite) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver detalle</a>@endif</div></article>
            @empty
                <p class="py-4 text-sm text-slate-500">No tienes solicitudes pendientes de acción.</p>
            @endforelse
        </div></section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-slate-900">Últimas solicitudes recibidas</h2><a href="{{ route('gestion-personas.bandeja') }}" class="shrink-0 text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver bandeja completa</a></div><div class="mt-4 divide-y divide-slate-100">
            @forelse($recent as $tramite)
                <a href="{{ route('tramites.show', $tramite) }}" class="flex flex-wrap items-center justify-between gap-4 py-3 hover:bg-slate-50"><div><p class="font-medium text-slate-900">{{ $tramite->codigo }} · {{ $tramite->tipoTramite->nombre }}</p><p class="mt-1 text-sm text-slate-600">{{ $tramite->unidadServicio->nombre }} · Actualizada {{ $tramite->updated_at->format('d-m-Y H:i') }}</p></div><x-status-badge :estado="$tramite->estadoTramite" /></a>
            @empty
                <p class="py-4 text-sm text-slate-500">Aún no se han recibido solicitudes dentro de tu alcance.</p>
            @endforelse
        </div></section>
    </div></div>
</x-app-layout>
