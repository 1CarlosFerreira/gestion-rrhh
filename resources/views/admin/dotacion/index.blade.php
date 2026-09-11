<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Dotación</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
            @endif

            <section class="rounded-xl bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <form class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" method="GET">
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

                    @can('dotacion.gestionar')
                        <div class="flex shrink-0 flex-wrap items-center gap-3">
                            <a class="inline-flex items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" href="{{ route('admin.dotacion.create') }}">Registrar vínculo</a>
                            @can('calidades_contractuales.ver')
                                <a class="text-sm text-indigo-700 underline" href="{{ route('admin.calidades.index') }}">Calidades</a>
                            @endcan
                        </div>
                    @endcan
                </div>
            </section>

            <div x-data="{ unidadAbierta: null }" class="grid items-start gap-3 lg:grid-cols-2">
                @forelse ($unidadesAgrupadas as $unidad)
                    @php
                        $vinculosUnidad = $vinculos->where('unidad_organizacional_id', $unidad->id);
                        $personasUnidad = $vinculosUnidad->pluck('persona_id')->unique()->count();
                        $etiquetaFuncionarios = $estado === 'VIGENTE' ? ($personasUnidad === 1 ? 'funcionario vigente' : 'funcionarios vigentes') : ($personasUnidad === 1 ? 'funcionario' : 'funcionarios');
                    @endphp
                    <section @class([
                        'overflow-hidden rounded-xl border bg-white shadow-sm',
                        'border-indigo-200 lg:col-span-2' => $unidad->es_encabezado_jerarquico,
                        'border-gray-200' => ! $unidad->es_encabezado_jerarquico,
                    ])>
                        <button type="button" @class([
                            'flex w-full items-start justify-between gap-4 px-4 py-4 text-left transition sm:px-5',
                            'bg-indigo-50 hover:bg-indigo-100' => $unidad->es_encabezado_jerarquico,
                            'hover:bg-gray-50' => ! $unidad->es_encabezado_jerarquico,
                        ]) @click="unidadAbierta = unidadAbierta === {{ $unidad->id }} ? null : {{ $unidad->id }}" :aria-expanded="(unidadAbierta === {{ $unidad->id }}).toString()">
                            <span class="flex min-w-0 items-start gap-3">
                                <svg class="mt-1 h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-90': unidadAbierta === {{ $unidad->id }} }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                                </svg>
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                        {{ $unidad->nombre }}
                                        @if ($unidad->es_encabezado_jerarquico)
                                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $unidad->tipo->nombre }}</span>
                                        @endif
                                    </span>
                                    @if ($unidad->ruta_jerarquica)
                                        <span class="mt-1 block text-sm text-gray-500">{{ $unidad->ruta_jerarquica }}</span>
                                    @endif
                                </span>
                            </span>
                            <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                {{ $personasUnidad }} {{ $etiquetaFuncionarios }}
                            </span>
                        </button>

                        <div x-cloak x-show="unidadAbierta === {{ $unidad->id }}" class="border-t border-gray-100 bg-gray-50/50 px-4 py-4 sm:px-5">
                            @if ($vinculosUnidad->isEmpty())
                                <div class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-5 text-sm text-gray-600">
                                    No existen personas vinculadas en la fecha seleccionada.
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach ($vinculosUnidad as $vinculo)
                                        @php($estadoVinculo = $vinculo->estadoEn($fecha)->value)
                                        <article class="rounded-lg border border-gray-200 bg-white p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-3">
                                                <div>
                                                    <h3 class="font-semibold text-gray-900">{{ $vinculo->persona->nombre_completo }}</h3>
                                                    <p class="mt-1 text-sm text-gray-500">RUT {{ $vinculo->persona->rut }}</p>
                                                </div>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $estadoVinculo === 'VIGENTE' ? 'bg-green-100 text-green-800' : ($estadoVinculo === 'FUTURO' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') }}">{{ $estadoVinculo }}</span>
                                            </div>

                                            <dl class="mt-3 grid gap-x-4 gap-y-2.5 text-sm sm:grid-cols-2">
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Cargo/función</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->cargo_funcion }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Calidad contractual</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->calidadContractual->nombre }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estamento</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->estamento->nombre }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Profesión</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->profesion?->nombre ?? '-' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Grado EUS</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->grado_eus ?? '-' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Vigencia</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->vigente_desde->format('d/m/Y') }} → {{ $vinculo->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Origen</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->origen->etiqueta() }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Cuenta</dt>
                                                    <dd class="mt-1 text-gray-800">{{ $vinculo->persona->user ? $vinculo->persona->user->email.' · '.($vinculo->persona->user->active ? 'Activa' : 'Inactiva') : 'Sin cuenta' }}</dd>
                                                </div>
                                            </dl>

                                            <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-3 text-sm">
                                                <a class="text-indigo-700 hover:underline" href="{{ route('admin.dotacion.persona', $vinculo->persona) }}">Ver ficha</a>
                                                @can('update', $vinculo)
                                                    <a class="text-indigo-700 hover:underline" href="{{ route('admin.dotacion.edit', $vinculo) }}">Editar</a>
                                                    @if (! $vinculo->vigente_hasta)
                                                        <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar este vínculo laboral?')">
                                                            @csrf
                                                            @method('PATCH')
                                                            <label class="text-xs text-gray-600">
                                                                Fecha de término
                                                                <input type="date" name="vigente_hasta" min="{{ $vinculo->vigente_desde->toDateString() }}" required class="mt-1 block w-36 rounded border-gray-300 text-xs">
                                                            </label>
                                                            <button class="text-indigo-700 hover:underline">Cerrar vínculo</button>
                                                        </form>
                                                    @endif
                                                @endcan
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @empty
                    <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-600 shadow-sm lg:col-span-2">No existen unidades accesibles para la fecha seleccionada.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>