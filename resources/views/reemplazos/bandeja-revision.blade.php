<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-950">Reemplazos · Gestión de Personas</h1>
            <p class="mt-1 text-sm text-gray-600">Revisa y gestiona las solicitudes de reemplazo recibidas.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <section aria-label="Indicadores de la bandeja" class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pendientes de revisión</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-950">{{ $indicadores['pendientes'] }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">En revisión</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-950">{{ $indicadores['en_revision'] }}</p>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Para generar documento</p>
                    <p class="mt-1 text-2xl font-semibold text-violet-950">{{ $indicadores['para_documento'] }}</p>
                </div>
            </section>

            <form method="GET" action="{{ route('gestion-personas.reemplazos.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(18rem,2fr)_minmax(12rem,1fr)_minmax(14rem,1fr)_auto] xl:items-end">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar</span>
                        <x-text-input name="buscar" value="{{ request('buscar') }}" placeholder="Código, nombre o RUT" class="mt-1 block w-full" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</span>
                        <select name="estado" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los estados</option>
                            @foreach($estados as $estado)
                                <option value="{{ $estado->codigo }}" @selected(request('estado') === $estado->codigo)>{{ $estado->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Unidad</span>
                        <select name="unidad_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas las unidades</option>
                            @foreach($unidades as $unidad)
                                <option value="{{ $unidad->id }}" @selected((string) request('unidad_id') === (string) $unidad->id)>{{ $unidad->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <x-primary-button>Buscar</x-primary-button>
                        @if(request()->filled('buscar') || request()->filled('estado') || request()->filled('unidad_id'))
                            <a href="{{ route('gestion-personas.reemplazos.index') }}" class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50">Limpiar filtros</a>
                        @endif
                    </div>
                </div>
            </form>

            <section aria-labelledby="solicitudes-title" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-4 py-3 sm:px-5">
                    <h2 id="solicitudes-title" class="text-sm font-semibold text-gray-900">Solicitudes recibidas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 sm:pl-5">Código</th>
                                <th class="px-4 py-3">Unidad</th>
                                <th class="px-4 py-3">Personas</th>
                                <th class="px-4 py-3">Períodos</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Fecha de envío</th>
                                <th class="px-4 py-3 text-right sm:pr-5"><span class="sr-only">Acción</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($tramites as $tramite)
                                @php
                                    $requiereRevision = in_array($tramite->estadoTramite->codigo, ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION'], true);
                                @endphp
                                <tr class="transition hover:bg-gray-50 {{ $requiereRevision ? 'bg-amber-50/50' : 'bg-white' }}">
                                    <td class="whitespace-nowrap px-4 py-3 align-top font-mono text-sm font-semibold text-gray-950 sm:pl-5">{{ $tramite->codigo }}</td>
                                    <td class="max-w-52 px-4 py-3 align-top font-medium leading-5 text-gray-800">{{ $tramite->unidadOrganizacional->nombre }}</td>
                                    <td class="px-4 py-3 align-top">
                                        <dl class="space-y-2">
                                            <div>
                                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Funcionario</dt>
                                                <dd class="mt-0.5 font-medium leading-5 text-gray-800">{{ $tramite->reemplazo->funcionario->nombre_completo }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Reemplazante</dt>
                                                <dd class="mt-0.5 font-medium leading-5 text-gray-800">{{ $tramite->reemplazo->reemplazante->nombre_completo }}</dd>
                                            </div>
                                        </dl>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-top">
                                        <dl class="space-y-2">
                                            <div>
                                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Período solicitado</dt>
                                                <dd class="mt-0.5 text-gray-800">{{ $tramite->reemplazo->fecha_funcionario_desde->format('d/m/Y') }} <span class="text-gray-400">al</span> {{ $tramite->reemplazo->fecha_funcionario_hasta->format('d/m/Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Cobertura del reemplazante</dt>
                                                <dd class="mt-0.5 text-gray-800">{{ $tramite->reemplazo->fecha_reemplazante_desde->format('d/m/Y') }} <span class="text-gray-400">al</span> {{ $tramite->reemplazo->fecha_reemplazante_hasta->format('d/m/Y') }}</dd>
                                            </div>
                                        </dl>
                                    </td>
                                    <td class="px-4 py-3 align-top"><x-status-badge :estado="$tramite->estadoTramite" /></td>
                                    <td class="whitespace-nowrap px-4 py-3 align-top">
                                        @if($tramite->submitted_at)
                                            <span class="block font-medium text-gray-800">{{ $tramite->submitted_at->format('d/m/Y') }}</span>
                                            <span class="mt-0.5 block text-xs text-gray-500">{{ $tramite->submitted_at->format('H:i') }} hrs.</span>
                                        @else
                                            <span class="text-gray-400">Sin fecha</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right align-top sm:pr-5">
                                        <a class="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $requiereRevision ? 'bg-indigo-700 text-white shadow-sm hover:bg-indigo-600' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}" href="{{ route('gestion-personas.reemplazos.show', $tramite) }}">
                                            {{ $requiereRevision ? 'Revisar' : 'Ver' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                        {{ $haySolicitudes ? 'No se encontraron resultados.' : 'No hay solicitudes pendientes.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{ $tramites->links() }}
        </div>
    </div>
</x-app-layout>
