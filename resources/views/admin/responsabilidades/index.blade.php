<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Responsables institucionales</h2>
                <p class="mt-1 text-sm text-gray-600">Administra las jefaturas titulares y subrogantes de cada unidad.</p>
            </div>

            @can('responsabilidades.gestionar')
                <a href="{{ route('admin.responsabilidades.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">+ Registrar responsable</a>
            @endcan
        </div>
    </x-slot>

    @php
        $responsabilidadesVigentes = $responsabilidades->filter(fn ($responsabilidad) => $responsabilidad->estaVigenteEn($fecha));
        $responsabilidadesHistoricas = $responsabilidades->reject(fn ($responsabilidad) => $responsabilidad->estaVigenteEn($fecha));
        $unidadesVigentes = $responsabilidadesVigentes->groupBy('unidad_organizacional_id');
        $unidadesHistoricas = $responsabilidadesHistoricas->groupBy('unidad_organizacional_id');
        $incluyeHistorial = request()->boolean('incluir_finalizados');
        $filtrosAvanzadosAplicados = request()->filled('unidad_id') || request()->filled('tipo') || request()->filled('fecha') || $incluyeHistorial;
        $hayFiltrosAplicados = request()->filled('persona') || $filtrosAvanzadosAplicados;
        $idsBloques = $unidadesVigentes->keys()->map(fn ($id) => 'vigente-'.$id)
            ->merge($unidadesHistoricas->keys()->map(fn ($id) => 'historica-'.$id))->values();
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <x-input-error :messages="$errors->all()" />

            <section class="rounded-xl bg-white p-4 shadow-sm sm:p-5" x-data="{ filtrosAbiertos: {{ $filtrosAvanzadosAplicados ? 'true' : 'false' }} }">
                <form method="GET" action="{{ route('admin.responsabilidades.index') }}" class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="block min-w-0 flex-1">
                            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Responsable</span>
                            <x-text-input name="persona" value="{{ request('persona') }}" placeholder="Nombre o RUT" class="block w-full text-sm" />
                        </label>

                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" x-on:click="filtrosAbiertos = ! filtrosAbiertos" x-bind:aria-expanded="filtrosAbiertos.toString()">
                            Filtros
                            @if ($hayFiltrosAplicados)
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">Activos</span>
                            @endif
                            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': filtrosAbiertos }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <x-secondary-button type="submit" class="justify-center">Buscar</x-secondary-button>

                        @if ($hayFiltrosAplicados)
                            <a href="{{ route('admin.responsabilidades.index') }}" class="inline-flex items-center justify-center px-2 py-2 text-sm font-medium text-indigo-700 hover:underline">Limpiar filtros</a>
                        @endif
                    </div>

                    <div x-cloak x-show="filtrosAbiertos" class="border-t border-gray-100 pt-4">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Unidad</span>
                                <select name="unidad_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todas las unidades</option>
                                    @foreach ($unidades as $unidad)
                                        <option value="{{ $unidad->id }}" @selected(request('unidad_id') == $unidad->id)>{{ $unidad->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Tipo</span>
                                <select name="tipo" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos los tipos</option>
                                    @foreach ($tipos as $tipo)
                                        <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Vigencia al</span>
                                <x-text-input type="date" name="fecha" value="{{ $fecha }}" class="block w-full text-sm" />
                            </label>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 sm:col-span-2 lg:col-span-4">
                                <input type="checkbox" name="incluir_finalizados" value="1" @checked($incluyeHistorial) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                Incluir historial
                            </label>
                        </div>
                    </div>
                </form>
            </section>

            <div class="space-y-6" x-data="{ abiertas: {}, cambiar(id) { this.abiertas[id] = ! this.abiertas[id] }, cambiarTodas(ids, valor) { ids.forEach((id) => this.abiertas[id] = valor) } }">
                <div class="flex justify-end gap-3 text-sm">
                    <button type="button" class="font-medium text-indigo-700 hover:underline" x-on:click="cambiarTodas(@js($idsBloques), true)">Expandir todos</button>
                    <span class="text-gray-300" aria-hidden="true">|</span>
                    <button type="button" class="font-medium text-gray-600 hover:text-gray-900 hover:underline" x-on:click="cambiarTodas(@js($idsBloques), false)">Contraer todos</button>
                </div>

                <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Responsables vigentes</h3>
                        <p class="mt-0.5 text-sm text-gray-500">Asignaciones activas al {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-800">{{ $responsabilidadesVigentes->count() }}</span>
                </div>

                <div class="space-y-3">
                    @forelse ($unidadesVigentes as $responsabilidadesUnidad)
                        @include('admin.responsabilidades.partials.unit-block', ['responsabilidadesUnidad' => $responsabilidadesUnidad, 'historica' => false, 'bloqueId' => 'vigente-'.$responsabilidadesUnidad->first()->unidad_organizacional_id])
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-8 text-center text-sm text-gray-600">No hay responsabilidades vigentes para los filtros seleccionados.</div>
                    @endforelse
                </div>
                </section>

                @if ($incluyeHistorial)
                    <section class="space-y-3 border-t border-gray-200 pt-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Historial de responsabilidades</h3>
                            <p class="mt-0.5 text-sm text-gray-500">Asignaciones que no están vigentes en la fecha consultada.</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">{{ $responsabilidadesHistoricas->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse ($unidadesHistoricas as $responsabilidadesUnidad)
                            @include('admin.responsabilidades.partials.unit-block', ['responsabilidadesUnidad' => $responsabilidadesUnidad, 'historica' => true, 'bloqueId' => 'historica-'.$responsabilidadesUnidad->first()->unidad_organizacional_id])
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center text-sm text-gray-500">No hay responsabilidades históricas para los filtros seleccionados.</div>
                        @endforelse
                    </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
