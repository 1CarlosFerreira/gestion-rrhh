<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Dotación</h2>
    </x-slot>

    <div class="py-10">
        <div
            class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8"
            x-data="{
                buscarUnidad: '',
                filtrosAbiertos: @js(request()->query() !== []),
                coincideUnidad(nombre) {
                    const normalizar = (valor) => valor.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    return normalizar(nombre).includes(normalizar(this.buscarUnidad.trim()))
                }
            }"
        >
            @if (session('status'))
                <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
            @endif

            <section class="rounded-xl bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative min-w-0 flex-1">
                        <label for="buscar-unidad" class="sr-only">Buscar unidad</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                        <input id="buscar-unidad" type="search" x-model.debounce.150ms="buscarUnidad" placeholder="Buscar unidad..." class="block w-full rounded-md border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        x-on:click="filtrosAbiertos = ! filtrosAbiertos"
                        x-bind:aria-expanded="filtrosAbiertos.toString()"
                        aria-controls="filtros-dotacion"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.75 4.25A.75.75 0 0 1 3.5 3.5h13a.75.75 0 0 1 .53 1.28l-5.28 5.28v4.19a.75.75 0 0 1-.416.671l-2.5 1.25A.75.75 0 0 1 7.75 15.5v-5.44L2.97 5.28a.75.75 0 0 1-.22-.53Z" /></svg>
                        Filtros
                        <svg class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': filtrosAbiertos }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </button>

                    @can('dotacion.gestionar')
                        <div class="flex flex-wrap items-center gap-3">
                            <a class="inline-flex flex-1 items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:flex-none" href="{{ route('admin.dotacion.create') }}">Registrar vínculo</a>
                            @can('calidades_contractuales.ver')
                                <a class="text-sm font-medium text-indigo-700 underline" href="{{ route('admin.calidades.index') }}">Calidades</a>
                            @endcan
                        </div>
                    @endcan
                </div>

                <div id="filtros-dotacion" x-cloak x-show="filtrosAbiertos" class="mt-5 border-t border-gray-200 pt-5">
                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" method="GET">
                        <x-text-input name="persona" value="{{ request('persona') }}" placeholder="RUT, nombres o apellidos" />

                        <select name="unidad_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Unidades accesibles</option>
                            @foreach ($unidades as $unidad)
                                <option value="{{ $unidad->id }}" @selected(request('unidad_id') == $unidad->id)>{{ $unidad->nombre }}</option>
                            @endforeach
                        </select>

                        <x-text-input type="date" name="fecha" value="{{ $fecha }}" />

                        <select name="estado" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="" @selected($estado === '')>Todos los estados</option>
                            @foreach ($estados as $opcionEstado)
                                <option value="{{ $opcionEstado->value }}" @selected($estado === $opcionEstado->value)>{{ $opcionEstado->value }}</option>
                            @endforeach
                        </select>

                        <select name="estamento_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Estamentos</option>
                            @foreach ($estamentos as $estamento)
                                <option value="{{ $estamento->id }}" @selected(request('estamento_id') == $estamento->id)>{{ $estamento->nombre }}</option>
                            @endforeach
                        </select>

                        <select name="profesion_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Profesiones</option>
                            @foreach ($profesiones as $profesion)
                                <option value="{{ $profesion->id }}" @selected(request('profesion_id') == $profesion->id)>{{ $profesion->nombre }}</option>
                            @endforeach
                        </select>

                        <select name="calidad_contractual_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Calidades</option>
                            @foreach ($calidades as $calidad)
                                <option value="{{ $calidad->id }}" @selected(request('calidad_contractual_id') == $calidad->id)>{{ $calidad->nombre }}</option>
                            @endforeach
                        </select>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="incluir_descendientes" value="1" @checked(request()->boolean('incluir_descendientes')) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                Descendientes
                            </label>
                            <x-secondary-button>Filtrar</x-secondary-button>
                        </div>
                    </form>
                </div>
            </section>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:grid">
                    <span>Unidad</span>
                    <span>Unidad padre</span>
                    <span class="text-center">Dotación</span>
                    <span class="text-right">Acción</span>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse ($unidadesAgrupadas as $unidad)
                        @php
                            $vinculosUnidad = $vinculos->where('unidad_organizacional_id', $unidad->id);
                            $vinculosVigentes = $vinculosUnidad->filter(fn ($vinculo) => $vinculo->estadoEn($fecha)->value === 'VIGENTE');
                            $personasVigentes = $vinculosVigentes->pluck('persona_id')->unique()->count();
                            $unidadPadre = $unidad->parent_id && $unidad->ruta_jerarquica
                                ? \Illuminate\Support\Str::afterLast($unidad->ruta_jerarquica, ' › ')
                                : 'Sin unidad padre';
                        @endphp

                        <section x-show="coincideUnidad(@js($unidad->nombre))" @class([
                            'grid gap-3 px-4 py-4 transition hover:bg-gray-50 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-center sm:gap-4 sm:px-5',
                            'bg-indigo-50/50' => $unidad->es_encabezado_jerarquico,
                        ])>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-semibold text-gray-900">{{ $unidad->nombre }}</h3>
                                    @if ($unidad->es_encabezado_jerarquico)
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $unidad->tipo->nombre }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="min-w-0 text-sm text-gray-600">
                                <span class="font-medium text-gray-500 sm:hidden">Unidad padre: </span>
                                <span class="break-words">{{ $unidadPadre }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-3 sm:justify-center">
                                <span class="text-sm font-medium text-gray-500 sm:hidden">Dotación</span>
                                <span class="inline-flex min-w-9 justify-center rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">{{ $personasVigentes }}</span>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                x-data
                                x-on:click="$dispatch('open-modal', 'dotacion-unidad-{{ $unidad->id }}')"
                            >Ver dotación</button>
                        </section>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-600">No existen unidades accesibles para la fecha seleccionada.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @foreach ($unidadesAgrupadas as $unidad)
        @php
            $vinculosUnidad = $vinculos->where('unidad_organizacional_id', $unidad->id);
            $vinculosVigentes = $vinculosUnidad->filter(fn ($vinculo) => $vinculo->estadoEn($fecha)->value === 'VIGENTE');
            $personasVigentes = $vinculosVigentes->pluck('persona_id')->unique()->count();
        @endphp

        <x-modal name="dotacion-unidad-{{ $unidad->id }}" maxWidth="2xl" focusable>
            <div
                class="flex max-h-[calc(100vh-3rem)] max-h-[calc(100dvh-3rem)] flex-col overflow-hidden"
                x-data="{
                    buscar: '',
                    coincide(texto) {
                        const normalizar = (valor) => valor.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                        return normalizar(texto).includes(normalizar(this.buscar.trim()))
                    }
                }"
            >
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-gray-900">{{ $unidad->nombre }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $personasVigentes }} {{ $personasVigentes === 1 ? 'funcionario vigente' : 'funcionarios vigentes' }}</p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" x-on:click="$dispatch('close')" aria-label="Cerrar modal">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 0 0-1.06-1.06L10 8.94 5.28 4.22Z" /></svg>
                    </button>
                </header>

                <div class="shrink-0 border-b border-gray-100 px-5 py-3 sm:px-6">
                    <label for="buscar-dotacion-{{ $unidad->id }}" class="sr-only">Buscar por nombre o RUT</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                        <input id="buscar-dotacion-{{ $unidad->id }}" type="search" x-model.debounce.150ms="buscar" placeholder="Buscar por nombre o RUT" class="block w-full rounded-md border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="min-h-0 flex-1 overscroll-contain overflow-y-auto px-5 py-4 sm:px-6">
                    @if ($vinculosUnidad->isEmpty())
                        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-600">No existen personas vinculadas en la fecha seleccionada.</div>
                    @else
                        <div class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach ($vinculosUnidad as $vinculo)
                                @php
                                    $estadoVinculo = $vinculo->estadoEn($fecha)->value;
                                    $textoBusqueda = $vinculo->persona->nombre_completo.' '.$vinculo->persona->rut;
                                @endphp
                                <article class="px-4 py-3" x-show="coincide(@js($textoBusqueda))">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="font-semibold text-gray-900">{{ $vinculo->persona->nombre_completo }}</h3>
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $estadoVinculo === 'VIGENTE' ? 'bg-green-100 text-green-800' : ($estadoVinculo === 'FUTURO' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') }}">{{ $estadoVinculo }}</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-gray-500">RUT {{ $vinculo->persona->rut }}</p>
                                        </div>

                                        <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                            <a class="font-medium text-indigo-700 hover:underline" href="{{ route('admin.dotacion.persona', $vinculo->persona) }}">Ver ficha</a>
                                            @can('update', $vinculo)
                                                <a class="font-medium text-indigo-700 hover:underline" href="{{ route('admin.dotacion.edit', $vinculo) }}">Editar</a>
                                                @if (! $vinculo->vigente_hasta)
                                                    <button type="button" class="font-medium text-red-700 hover:underline" x-on:click="$dispatch('close-modal', 'dotacion-unidad-{{ $unidad->id }}'); $dispatch('open-modal', 'cerrar-vinculo-{{ $vinculo->id }}')">Cerrar vínculo</button>
                                                @endif
                                            @endcan
                                        </div>
                                    </div>

                                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        <div class="min-w-0"><dt class="text-xs font-medium text-gray-500">Cargo/función</dt><dd class="truncate text-gray-800" title="{{ $vinculo->cargo_funcion }}">{{ $vinculo->cargo_funcion }}</dd></div>
                                        <div class="min-w-0"><dt class="text-xs font-medium text-gray-500">Calidad contractual</dt><dd class="truncate text-gray-800" title="{{ $vinculo->calidadContractual->nombre }}">{{ $vinculo->calidadContractual->nombre }}</dd></div>
                                        <div class="min-w-0"><dt class="text-xs font-medium text-gray-500">Profesión</dt><dd class="truncate text-gray-800" title="{{ $vinculo->profesion?->nombre ?? '-' }}">{{ $vinculo->profesion?->nombre ?? '-' }}</dd></div>
                                        <div><dt class="text-xs font-medium text-gray-500">Grado EUS</dt><dd class="text-gray-800">{{ $vinculo->grado_eus ?? '-' }}</dd></div>
                                    </dl>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                <footer class="flex shrink-0 justify-end border-t border-gray-200 px-5 py-3 sm:px-6">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Cerrar</x-secondary-button>
                </footer>
            </div>
        </x-modal>

        @foreach ($vinculosUnidad as $vinculo)
            @can('update', $vinculo)
                @if (! $vinculo->vigente_hasta)
                    <x-modal name="cerrar-vinculo-{{ $vinculo->id }}" maxWidth="md" focusable>
                        <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="p-6" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                            @csrf
                            @method('PATCH')

                            <h2 class="text-lg font-semibold text-gray-900">Cerrar vínculo laboral</h2>
                            <p class="mt-2 text-sm text-gray-600">Indique la fecha de término del vínculo de <span class="font-medium text-gray-800">{{ $vinculo->persona->nombre_completo }}</span> con {{ $unidad->nombre }}.</p>

                            <div class="mt-5">
                                <x-input-label for="vigente-hasta-{{ $vinculo->id }}" value="Fecha de término" />
                                <x-text-input id="vigente-hasta-{{ $vinculo->id }}" type="date" name="vigente_hasta" min="{{ $vinculo->vigente_desde->toDateString() }}" class="mt-1 block w-full" required />
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                                <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                                <x-danger-button x-bind:disabled="submitting">Cerrar vínculo</x-danger-button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endcan
        @endforeach
    @endforeach
</x-app-layout>
