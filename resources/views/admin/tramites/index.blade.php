<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-brand-text">Trámites</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-input-error :messages="$errors->all()" />

            @php
                $filtrosSecundariosActivos = collect(['unidad', 'creador', 'fecha_desde', 'fecha_hasta'])
                    ->filter(fn ($filtro) => request()->filled($filtro))
                    ->count();
            @endphp

            <form method="GET" action="{{ route('admin.tramites.index') }}" x-data="{ filtrosAbiertos: {{ $filtrosSecundariosActivos > 0 ? 'true' : 'false' }} }">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                        <label class="sm:col-span-2 lg:col-span-5">
                            <span class="mb-1 block text-xs font-medium text-gray-600">Búsqueda</span>
                            <x-text-input name="buscar" type="search" :value="request('buscar')" class="w-full" placeholder="Código, funcionario o reemplazante" />
                        </label>

                        <label class="lg:col-span-3">
                            <span class="mb-1 block text-xs font-medium text-gray-600">Tipo de trámite</span>
                            <select name="tipo" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                <option value="">Todos</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((string) request('tipo') === (string) $tipo->id)>{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="lg:col-span-3">
                            <span class="mb-1 block text-xs font-medium text-gray-600">Estado</span>
                            <select name="estado" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                <option value="">Todos</option>
                                @foreach ($estados as $estado)
                                    <option value="{{ $estado->id }}" @selected((string) request('estado') === (string) $estado->id)>
                                        {{ request()->filled('tipo') ? $estado->nombre : $estado->tipoTramite->nombre.' — '.$estado->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <x-primary-button type="submit" class="justify-center sm:self-end lg:col-span-1">Filtrar</x-primary-button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-100 pt-3">
                        <button
                            type="button"
                            x-on:click="filtrosAbiertos = ! filtrosAbiertos"
                            x-bind:aria-expanded="filtrosAbiertos.toString()"
                            aria-controls="filtros-secundarios-tramites"
                            class="inline-flex items-center gap-1 text-sm font-medium text-brand-primary-dark hover:underline focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2"
                        >
                            <span>Más filtros{{ $filtrosSecundariosActivos > 0 ? ' ('.$filtrosSecundariosActivos.')' : '' }}</span>
                            <span x-show="! filtrosAbiertos" aria-hidden="true">▾</span>
                            <span x-cloak x-show="filtrosAbiertos" aria-hidden="true">▴</span>
                        </button>
                        <a href="{{ route('admin.tramites.index') }}" class="text-sm text-gray-500 hover:text-brand-primary-dark hover:underline">Limpiar filtros</a>
                    </div>

                    <div id="filtros-secundarios-tramites" x-cloak x-show="filtrosAbiertos" class="mt-3 grid gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-600">Unidad organizacional</span>
                            <select name="unidad" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                <option value="">Todas</option>
                                @foreach ($unidades as $unidad)
                                    <option value="{{ $unidad->id }}" @selected((string) request('unidad') === (string) $unidad->id)>{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-600">Creado por</span>
                            <select name="creador" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                <option value="">Todos</option>
                                @foreach ($creadores as $creador)
                                    <option value="{{ $creador->id }}" @selected((string) request('creador') === (string) $creador->id)>{{ $creador->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-600">Fecha desde</span>
                            <x-text-input name="fecha_desde" type="date" :value="request('fecha_desde')" class="w-full" />
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-600">Fecha hasta</span>
                            <x-text-input name="fecha_hasta" type="date" :value="request('fecha_hasta')" class="w-full" />
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p><span class="font-semibold text-brand-text">{{ $tramites->total() }}</span> {{ $tramites->total() === 1 ? 'trámite encontrado' : 'trámites encontrados' }}</p>
                        @if ($tramites->total() > 0)
                            <p class="mt-0.5 text-xs">Mostrando {{ $tramites->firstItem() }}–{{ $tramites->lastItem() }} de {{ $tramites->total() }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-500">Orden</span>
                            <select name="orden" x-on:change="$el.form.submit()" class="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                <option value="recientes" @selected(request('orden', 'recientes') === 'recientes')>Más recientes</option>
                                <option value="antiguos" @selected(request('orden') === 'antiguos')>Más antiguos</option>
                            </select>
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-medium text-gray-500">Mostrar</span>
                            <select name="per_page" x-on:change="$el.form.submit()" class="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                @foreach ([20, 50, 100] as $cantidad)
                                    <option value="{{ $cantidad }}" @selected((int) request('per_page', 20) === $cantidad)>{{ $cantidad }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
            </form>

            <div class="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Persona / asunto</th>
                            <th class="px-4 py-3">Unidad</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="hidden px-4 py-3 lg:table-cell">Creado por</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tramites as $tramite)
                            <tr class="align-top hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-brand-text">
                                    @if ($tramite->detalleUrl)
                                        <a href="{{ $tramite->detalleUrl }}" class="hover:text-brand-primary-dark hover:underline">{{ $tramite->codigo }}</a>
                                    @else
                                        {{ $tramite->codigo }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $tramite->tipo }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-brand-text">{{ $tramite->personaAsunto }}</p>
                                    @if ($tramite->personaAsuntoSecundario)
                                        <p class="mt-0.5 hidden text-xs text-gray-500 lg:block">Reemplazante: {{ $tramite->personaAsuntoSecundario }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $tramite->unidad }}</td>
                                <td class="px-4 py-3"><x-status-badge :estado="$tramite->estado" /></td>
                                <td class="hidden px-4 py-3 text-gray-700 lg:table-cell">{{ $tramite->creador }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $tramite->fecha->format('d-m-Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($tramite->detalleUrl)
                                        <a href="{{ $tramite->detalleUrl }}" class="font-medium text-brand-primary-dark hover:underline">Ver</a>
                                    @else
                                        <span class="text-xs text-gray-400">No disponible</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No se encontraron trámites con los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 md:hidden">
                @forelse ($tramites as $tramite)
                    <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-brand-text">{{ $tramite->codigo }}</p>
                            <x-status-badge :estado="$tramite->estado" />
                        </div>
                        <dl class="mt-3 grid gap-2 text-sm">
                            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tipo</dt><dd class="mt-0.5 text-gray-800">{{ $tramite->tipo }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Persona / asunto</dt><dd class="mt-0.5 font-medium text-gray-900">{{ $tramite->personaAsunto }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Unidad</dt><dd class="mt-0.5 text-gray-800">{{ $tramite->unidad }}</dd></div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</dt><dd class="mt-0.5 text-gray-800">{{ $tramite->fecha->format('d-m-Y') }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Creado por</dt><dd class="mt-0.5 truncate text-gray-800">{{ $tramite->creador }}</dd></div>
                            </div>
                        </dl>
                        <div class="mt-4 border-t border-gray-100 pt-3 text-right">
                            @if ($tramite->detalleUrl)
                                <a href="{{ $tramite->detalleUrl }}" class="font-medium text-brand-primary-dark hover:underline">Ver</a>
                            @else
                                <span class="text-xs text-gray-400">Detalle no disponible</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">No se encontraron trámites con los filtros seleccionados.</p>
                @endforelse
            </div>

            @if ($tramites->hasPages())
                <div>{{ $tramites->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
