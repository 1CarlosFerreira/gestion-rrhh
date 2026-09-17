@php
    $detalle = $tramite->reemplazo;
    $diasTotales = $detalle->diasFuncionario();
    $diasCubiertos = $detalle->diasReemplazante();
    $diasSinCobertura = $detalle->diasSinCobertura();
    $porcentajeCubierto = $diasTotales > 0 ? min(100, max(0, round(($diasCubiertos / $diasTotales) * 100, 1))) : 0;
    $vinculoFuncionario = $detalle->funcionario->relationLoaded('vinculosDotacion')
        ? $detalle->funcionario->vinculosDotacion->first()
        : null;
    $mostrarCabecera = $mostrarCabecera ?? true;
    $mostrarContenido = $mostrarContenido ?? true;
    $cabeceraCompacta = $cabeceraCompacta ?? false;
@endphp

@if($mostrarCabecera)
    <a href="{{ $volverHref }}" class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-700 transition hover:text-indigo-900">
        <span aria-hidden="true">←</span> {{ $volverTexto }}
    </a>

    <header class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Solicitud de Reemplazo</p>
                <div class="mt-1 flex flex-wrap items-center gap-2.5">
                    <h1 class="whitespace-nowrap font-mono text-xl font-semibold text-gray-950 sm:text-2xl">{{ $tramite->codigo }}</h1>
                    <x-status-badge :estado="$tramite->estadoTramite" />
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ $tramite->unidadOrganizacional->nombre }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-3 sm:items-end">
                @if($mostrarGenerarDocumento ?? false)
                    @can('generar-documento-reemplazo', $tramite)
                        <form method="POST" action="{{ route('reemplazos.documentos.store', $tramite) }}">
                            @csrf
                            <x-primary-button>Generar documento</x-primary-button>
                        </form>
                    @endcan
                @endif
                @if(($documentoDescarga ?? null) && ($mostrarDescargarDocumento ?? false))
                    @can('generar-documento-reemplazo', $tramite)
                        <a href="{{ route('reemplazos.documentos.download', [$tramite, $documentoDescarga]) }}" class="inline-flex items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-600">Descargar PDF</a>
                    @endcan
                @endif
                @if($mostrarIniciarRevision ?? false)
                    <form method="POST" action="{{ route('gestion-personas.reemplazos.start', $tramite) }}">
                        @csrf
                        <x-primary-button>Iniciar revisión</x-primary-button>
                    </form>
                @endif
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $fechaEtiqueta }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-800">{{ $fechaValor?->format('d/m/Y H:i') ?? 'No informada' }}</p>
                </div>
            </div>
        </div>
        @if($cabeceraCompacta)
            <dl class="mt-4 grid gap-3 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-gray-500">Funcionario</dt><dd class="mt-0.5 font-semibold text-gray-900">{{ $detalle->funcionario->nombre_completo }}</dd></div>
                <div><dt class="text-xs text-gray-500">Reemplazante</dt><dd class="mt-0.5 font-semibold text-gray-900">{{ $detalle->reemplazante->nombre_completo }}</dd></div>
                <div><dt class="text-xs text-gray-500">Período total</dt><dd class="mt-0.5 font-medium text-gray-800">{{ $detalle->fecha_funcionario_desde->format('d/m/Y') }} al {{ $detalle->fecha_funcionario_hasta->format('d/m/Y') }}</dd></div>
                <div><dt class="text-xs text-gray-500">Cobertura</dt><dd class="mt-0.5 font-medium text-gray-800">{{ $diasCubiertos }} de {{ $diasTotales }} días · {{ number_format($porcentajeCubierto, $porcentajeCubierto == floor($porcentajeCubierto) ? 0 : 1, ',', '.') }}%</dd></div>
            </dl>
        @endif
    </header>
@endif

@if($mostrarContenido)
<section aria-labelledby="resumen-reemplazo-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 id="resumen-reemplazo-title" class="text-base font-semibold text-gray-950">Antecedentes de la solicitud</h2>
    <div class="mt-4 grid gap-3 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Funcionario reemplazado</p>
            <p class="mt-2 text-base font-semibold text-gray-950">{{ $detalle->funcionario->nombre_completo }}</p>
            <p class="mt-0.5 font-mono text-sm font-medium text-gray-600">{{ $detalle->funcionario->rut }}</p>
            @if($vinculoFuncionario)
                <dl class="mt-4 grid gap-3 border-t border-gray-200 pt-3 text-xs sm:grid-cols-3">
                    <div><dt class="font-medium text-gray-400">Cargo</dt><dd class="mt-0.5 text-gray-700">{{ $vinculoFuncionario->cargo_funcion }}</dd></div>
                    <div><dt class="font-medium text-gray-400">Profesión</dt><dd class="mt-0.5 text-gray-700">{{ $vinculoFuncionario->profesion?->nombre ?? 'No informada' }}</dd></div>
                    <div><dt class="font-medium text-gray-400">Estamento</dt><dd class="mt-0.5 text-gray-700">{{ $vinculoFuncionario->estamento?->nombre ?? 'No informado' }}</dd></div>
                </dl>
            @endif
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Antecedentes propuestos del reemplazante</p>
            <p class="mt-2 text-base font-semibold text-gray-950">{{ $detalle->reemplazante->nombre_completo }}</p>
            <p class="mt-0.5 font-mono text-sm font-medium text-gray-600">{{ $detalle->reemplazante->rut }}</p>
            <dl class="mt-4 grid gap-3 border-t border-gray-200 pt-3 text-xs sm:grid-cols-2">
                <div><dt class="font-medium text-gray-400">Estamento</dt><dd class="mt-0.5 text-gray-700">{{ $detalle->reemplazanteEstamento?->nombre ?? 'No informado' }}</dd></div>
                <div><dt class="font-medium text-gray-400">Profesión</dt><dd class="mt-0.5 text-gray-700">{{ $detalle->reemplazanteProfesion?->nombre ?? 'No corresponde / no informada' }}</dd></div>
                <div><dt class="font-medium text-gray-400">Calidad contractual</dt><dd class="mt-0.5 text-gray-700">{{ $detalle->reemplazanteCalidadContractual?->nombre ?? 'No informada' }}</dd></div>
                <div><dt class="font-medium text-gray-400">Cargo / función</dt><dd class="mt-0.5 text-gray-700">{{ $detalle->reemplazante_cargo_funcion ?? 'No informado' }}</dd></div>
            </dl>
        </div>
    </div>
    <dl class="mt-3 grid gap-3 rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 sm:grid-cols-[minmax(12rem,1fr)_minmax(0,2fr)]">
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Tipo de reemplazo</dt>
            <dd class="mt-1 text-sm font-semibold text-indigo-950">{{ $detalle->tipoReemplazo->nombre }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Justificación</dt>
            <dd class="mt-1 whitespace-pre-line text-sm leading-5 text-gray-700">{{ $detalle->justificacion }}</dd>
        </div>
    </dl>
</section>

<section aria-labelledby="cobertura-reemplazo-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 id="cobertura-reemplazo-title" class="text-base font-semibold text-gray-950">Períodos y cobertura</h2>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Período total solicitado</p>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->fecha_funcionario_desde->format('d/m/Y') }} <span class="text-gray-400">al</span> {{ $detalle->fecha_funcionario_hasta->format('d/m/Y') }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Período efectivo del reemplazante</p>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->fecha_reemplazante_desde->format('d/m/Y') }} <span class="text-gray-400">al</span> {{ $detalle->fecha_reemplazante_hasta->format('d/m/Y') }}</p>
        </div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-4 text-xs font-medium text-gray-600">
        <span>Cobertura del período solicitado</span>
        <span>{{ number_format($porcentajeCubierto, $porcentajeCubierto == floor($porcentajeCubierto) ? 0 : 1, ',', '.') }}% cubierto</span>
    </div>
    <div class="mt-2 flex h-3 overflow-hidden rounded-full bg-amber-200" role="img" aria-label="{{ $diasCubiertos }} de {{ $diasTotales }} días cubiertos">
        <div class="h-full bg-emerald-500" style="width: {{ $porcentajeCubierto }}%"></div>
    </div>
    <dl class="mt-4 grid gap-2 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500">Días totales</dt><dd class="text-lg font-semibold text-gray-900">{{ $diasTotales }}</dd></div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2"><dt class="text-xs text-emerald-700">Días cubiertos</dt><dd class="text-lg font-semibold text-emerald-900">{{ $diasCubiertos }}</dd></div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2"><dt class="text-xs text-amber-700">Días sin cobertura</dt><dd class="text-lg font-semibold text-amber-900">{{ $diasSinCobertura }}</dd></div>
    </dl>
</section>
@endif
