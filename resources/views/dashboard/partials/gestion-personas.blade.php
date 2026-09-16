<section class="rounded-xl border border-indigo-100 bg-white p-5 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Hola, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-slate-600">Revisa y gestiona los trámites que requieren atención de Gestión de Personas.</p>
</section>

@php
    $indicadoresReemplazos = [
        ['clave' => 'pendientes', 'estado' => 'ENVIADA_GESTION_PERSONAS', 'nombre' => 'Pendientes de revisión', 'clases' => 'border-amber-200 bg-amber-50 text-amber-950', 'etiqueta' => 'text-amber-700'],
        ['clave' => 'en_revision', 'estado' => 'EN_REVISION', 'nombre' => 'En revisión', 'clases' => 'border-blue-200 bg-blue-50 text-blue-950', 'etiqueta' => 'text-blue-700'],
        ['clave' => 'para_documento', 'estado' => 'LISTA_GENERAR_DOCUMENTO', 'nombre' => 'Listos para generar documento', 'clases' => 'border-indigo-200 bg-indigo-50 text-indigo-950', 'etiqueta' => 'text-indigo-700'],
    ];
@endphp

<section aria-label="Indicadores de Reemplazos" class="grid gap-3 sm:grid-cols-3">
    @foreach ($indicadoresReemplazos as $indicador)
        <a href="{{ route('gestion-personas.reemplazos.index', ['pestana' => 'activos', 'estado' => $indicador['estado']]) }}" class="rounded-xl border px-4 py-3 shadow-sm transition hover:shadow {{ $indicador['clases'] }}">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $indicador['etiqueta'] }}">{{ $indicador['nombre'] }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $panelGestionPersonas['reemplazos']['indicadores'][$indicador['clave']] }}</p>
        </a>
    @endforeach
</section>

<section aria-labelledby="gestion-personas-atencion-title" class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <header class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="gestion-personas-atencion-title" class="text-lg font-semibold text-gray-900">Requieren tu atención</h2>
            <p class="mt-0.5 text-sm text-gray-500">Próximas acciones operativas de Reemplazos.</p>
        </div>
        <a href="{{ route('gestion-personas.reemplazos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Ver todos los reemplazos</a>
    </header>

    <div class="divide-y divide-gray-200">
        @forelse ($panelGestionPersonas['reemplazos']['requieren_atencion'] as $tramite)
            @php
                $estado = $tramite->estadoTramite->codigo;
                $accion = match ($estado) {
                    'ENVIADA_GESTION_PERSONAS' => 'Revisar',
                    'EN_REVISION' => 'Continuar revisión',
                    'LISTA_GENERAR_DOCUMENTO' => 'Generar documento',
                    default => 'Ver',
                };
                $fechaRelevante = $tramite->submitted_at ?? $tramite->updated_at;
            @endphp
            <article class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto] lg:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold text-gray-900">{{ $tramite->codigo }}</span>
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $tramite->estadoTramite->nombre }}</span>
                    </div>
                    <p class="mt-1 truncate text-sm text-gray-600">{{ $tramite->unidadOrganizacional->nombre }}</p>
                </div>
                <div class="min-w-0 text-sm">
                    <p class="truncate font-medium text-gray-800">{{ $tramite->reemplazo->funcionario->nombre_completo }} → {{ $tramite->reemplazo->reemplazante?->nombre_completo ?? 'Sin reemplazante' }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $fechaRelevante?->format('d/m/Y') ?? '-' }}</p>
                </div>
                <a href="{{ route('gestion-personas.reemplazos.show', $tramite) }}" class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">{{ $accion }} <span class="ml-1" aria-hidden="true">→</span></a>
            </article>
        @empty
            <p class="px-5 py-8 text-center text-sm text-gray-600">No hay reemplazos que requieran atención.</p>
        @endforelse
    </div>
</section>
