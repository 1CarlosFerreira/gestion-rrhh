<x-app-layout>
    @php
        $detalle = $tramite->reemplazo;
        $estadosReemplazo = [
            'BORRADOR' => 'Borrador',
            'ENVIADA_GESTION_PERSONAS' => 'Enviada a Gestión de Personas',
            'EN_REVISION' => 'En revisión',
            'DEVUELTA_PARA_CORRECCION' => 'Devuelta para corrección',
            'LISTA_GENERAR_DOCUMENTO' => 'Lista para generar documento',
            'DOCUMENTO_GENERADO' => 'Documento generado',
            'FORMALIZADA' => 'Formalizada',
        ];
        $estadoActual = $tramite->estadoTramite->codigo;
        $posicionActual = array_search($estadoActual, array_keys($estadosReemplazo), true);
        $periodosCompletos = $detalle->fecha_funcionario_desde
            && $detalle->fecha_funcionario_hasta
            && $detalle->fecha_reemplazante_desde
            && $detalle->fecha_reemplazante_hasta;
        $diasSolicitados = $periodosCompletos ? $detalle->diasFuncionario() : 0;
        $diasCubiertos = $periodosCompletos ? $detalle->diasReemplazante() : 0;
        $diasSinCobertura = $periodosCompletos ? $detalle->diasSinCobertura() : 0;
        $porcentajeCubierto = $diasSolicitados > 0
            ? min(100, max(0, round(($diasCubiertos / $diasSolicitados) * 100, 1)))
            : 0;
        $periodosCubiertos = $periodosCompletos
            ? [['desde' => $detalle->fecha_reemplazante_desde, 'hasta' => $detalle->fecha_reemplazante_hasta]]
            : [];
        $mesesCalendario = [];

        if ($periodosCompletos) {
            $mes = $detalle->fecha_funcionario_desde->copy()->startOfMonth();
            $ultimoMes = $detalle->fecha_funcionario_hasta->copy()->startOfMonth();

            while ($mes->lte($ultimoMes)) {
                $mesesCalendario[] = $mes->copy();
                $mes->addMonth();
            }
        }
    @endphp

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-700 transition hover:text-indigo-900">
                <span aria-hidden="true">←</span> Volver al Inicio
            </a>

            <header class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $tramite->tipoTramite->nombre }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2.5">
                            <h1 class="whitespace-nowrap font-mono text-xl font-semibold text-gray-950 sm:text-2xl">{{ $tramite->codigo }}</h1>
                            <x-status-badge :estado="$tramite->estadoTramite" />
                        </div>
                        <p class="mt-2 text-sm text-gray-600">{{ $tramite->unidadOrganizacional->nombre }}</p>
                    </div>
                    <div class="shrink-0 text-left sm:text-right">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Última actualización</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $tramite->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </header>

            <section aria-labelledby="estado-tramite-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="estado-tramite-title" class="text-base font-semibold text-gray-950">Estado del trámite</h2>
                    <x-status-badge :estado="$tramite->estadoTramite" />
                </div>
                <ol class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Progreso de la solicitud">
                    @foreach($estadosReemplazo as $codigo => $nombre)
                        @php
                            $posicion = $loop->index;
                            $esActual = $codigo === $estadoActual;
                            $estaCompletado = $posicionActual !== false && $posicion < $posicionActual;
                        @endphp
                        <li class="flex items-start gap-3 rounded-lg border px-3 py-3 {{ $esActual ? 'border-indigo-300 bg-indigo-50' : ($estaCompletado ? 'border-emerald-200 bg-emerald-50/60' : 'border-gray-200 bg-gray-50/60') }}">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $esActual ? 'bg-indigo-700 text-white' : ($estaCompletado ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600') }}">
                                {{ $estaCompletado ? '✓' : $loop->iteration }}
                            </span>
                            <span class="text-xs font-semibold leading-5 {{ $esActual ? 'text-indigo-900' : ($estaCompletado ? 'text-emerald-900' : 'text-gray-500') }}">{{ $nombre }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section aria-labelledby="antecedentes-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 id="antecedentes-title" class="text-base font-semibold text-gray-950">Antecedentes del reemplazo</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Funcionario reemplazado</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->funcionario?->nombre_completo ?? 'No informado' }}</dd>
                        @if($detalle->funcionario)<dd class="mt-0.5 text-xs text-gray-500">{{ $detalle->funcionario->rut }}</dd>@endif
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reemplazante</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->reemplazante?->nombre_completo ?? 'No informado' }}</dd>
                        @if($detalle->reemplazante)<dd class="mt-0.5 text-xs text-gray-500">{{ $detalle->reemplazante->rut }}</dd>@endif
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo de reemplazo</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->tipoReemplazo?->nombre ?? 'No informado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Unidad</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $tramite->unidadOrganizacional->nombre }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="periodos-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 id="periodos-title" class="text-base font-semibold text-gray-950">Períodos y cobertura</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Período del funcionario</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->fecha_funcionario_desde?->format('d/m/Y') ?? 'No informado' }} <span class="text-gray-400">al</span> {{ $detalle->fecha_funcionario_hasta?->format('d/m/Y') ?? 'No informado' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Período efectivo del reemplazante</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $detalle->fecha_reemplazante_desde?->format('d/m/Y') ?? 'No informado' }} <span class="text-gray-400">al</span> {{ $detalle->fecha_reemplazante_hasta?->format('d/m/Y') ?? 'No informado' }}</p>
                    </div>
                </div>
                @if($periodosCompletos)
                    <div class="mt-5">
                        <div class="flex items-center justify-between gap-4 text-xs font-medium text-gray-600">
                            <span>Cobertura del período solicitado</span>
                            <span>{{ number_format($porcentajeCubierto, $porcentajeCubierto == floor($porcentajeCubierto) ? 0 : 1, ',', '.') }}% cubierto</span>
                        </div>
                        <div class="mt-2 flex h-3 w-full overflow-hidden rounded-full bg-amber-200" role="img" aria-label="{{ $diasCubiertos }} de {{ $diasSolicitados }} días cubiertos; {{ $diasSinCobertura }} días sin cobertura">
                            <div class="h-full bg-emerald-500 transition-all" style="width: {{ $porcentajeCubierto }}%"></div>
                        </div>

                        <dl class="mt-4 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-500">Período solicitado</dt>
                                <dd class="mt-0.5 text-base font-semibold text-gray-900">{{ $diasSolicitados }} días</dd>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                                <dt class="text-xs text-emerald-700">Cubiertos</dt>
                                <dd class="mt-0.5 text-base font-semibold text-emerald-900">{{ $diasCubiertos }} días</dd>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                                <dt class="text-xs text-amber-700">Sin cobertura</dt>
                                <dd class="mt-0.5 text-base font-semibold text-amber-900">{{ $diasSinCobertura }} días</dd>
                            </div>
                        </dl>

                        <details class="group mt-4 rounded-lg border border-gray-200 bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-indigo-700 marker:content-none">
                                <span>Ver calendario</span>
                                <span class="transition-transform group-open:rotate-180" aria-hidden="true">⌄</span>
                            </summary>
                            <div class="border-t border-gray-200 px-4 py-4">
                                <div class="mb-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600" aria-label="Leyenda del calendario">
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-emerald-500"></span>Cubierto</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-amber-200"></span>Sin cobertura</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-gray-100 ring-1 ring-inset ring-gray-200"></span>Fuera del período</span>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach($mesesCalendario as $mesCalendario)
                                        @php
                                            $primerDia = $mesCalendario->copy()->startOfMonth();
                                            $diasDelMes = $mesCalendario->daysInMonth;
                                            $espaciosIniciales = $primerDia->dayOfWeekIso - 1;
                                        @endphp
                                        <section aria-label="Calendario de {{ $mesCalendario->translatedFormat('F Y') }}" class="rounded-lg border border-gray-200 p-3">
                                            <h3 class="text-center text-sm font-semibold capitalize text-gray-900">{{ $mesCalendario->translatedFormat('F Y') }}</h3>
                                            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold uppercase text-gray-400" aria-hidden="true">
                                                @foreach(['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'] as $diaSemana)
                                                    <span>{{ $diaSemana }}</span>
                                                @endforeach
                                            </div>
                                            <div class="mt-1 grid grid-cols-7 gap-1 text-center text-xs">
                                                @for($espacio = 0; $espacio < $espaciosIniciales; $espacio++)
                                                    <span aria-hidden="true"></span>
                                                @endfor
                                                @for($numeroDia = 1; $numeroDia <= $diasDelMes; $numeroDia++)
                                                    @php
                                                        $fechaDia = $mesCalendario->copy()->day($numeroDia);
                                                        $estaEnPeriodo = $fechaDia->betweenIncluded($detalle->fecha_funcionario_desde, $detalle->fecha_funcionario_hasta);
                                                        $estaCubierto = $estaEnPeriodo && collect($periodosCubiertos)->contains(
                                                            fn ($periodo) => $fechaDia->betweenIncluded($periodo['desde'], $periodo['hasta'])
                                                        );
                                                        $claseDia = $estaCubierto
                                                            ? 'bg-emerald-500 font-semibold text-white'
                                                            : ($estaEnPeriodo ? 'bg-amber-200 font-semibold text-amber-950' : 'bg-gray-100 text-gray-400');
                                                        $estadoDia = $estaCubierto ? 'cubierto' : ($estaEnPeriodo ? 'sin cobertura' : 'fuera del período');
                                                    @endphp
                                                    <span title="{{ $fechaDia->format('d/m/Y') }}: {{ $estadoDia }}" aria-label="{{ $fechaDia->format('d/m/Y') }}, {{ $estadoDia }}" class="flex aspect-square min-h-7 items-center justify-center rounded {{ $claseDia }}">{{ $numeroDia }}</span>
                                                @endfor
                                            </div>
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    </div>
                @endif
            </section>

            <section aria-labelledby="justificacion-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 id="justificacion-title" class="text-base font-semibold text-gray-950">Justificación</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $detalle->justificacion ?: 'No informada.' }}</p>
            </section>

            <section aria-labelledby="adjuntos-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="adjuntos-title" class="text-base font-semibold text-gray-950">Adjuntos</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $tramite->adjuntos->count() }}</span>
                </div>
                <div class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200">
                    @forelse($tramite->adjuntos as $adjunto)
                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $adjunto->original_name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $adjunto->tipoDocumento?->nombre ?? 'Sin tipo documental' }} · versión {{ $adjunto->version }}</p>
                            </div>
                            @can('tramites.adjuntos.descargar')
                                <a class="inline-flex shrink-0 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100" href="{{ route('reemplazos.adjuntos.download', [$tramite, $adjunto]) }}">Descargar</a>
                            @endcan
                        </div>
                    @empty
                        <p class="px-4 py-5 text-sm text-gray-500">Sin documentos adjuntos.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
