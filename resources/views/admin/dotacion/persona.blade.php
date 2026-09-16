<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">Ficha laboral</h2></x-slot>

    @php
        $vinculosActuales = $persona->vinculosDotacion->filter(fn ($vinculo) => $vinculo->estadoEn(today())->value !== 'FINALIZADO');
        $vinculosHistoricos = $persona->vinculosDotacion->filter(fn ($vinculo) => $vinculo->estadoEn(today())->value === 'FINALIZADO');
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif
            <x-input-error :messages="$errors->all()" />

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Expediente laboral</p>
                        <h2 class="mt-1 truncate text-lg font-semibold text-gray-950">{{ $persona->nombre_completo }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                            <span><strong class="font-medium text-gray-700">RUT:</strong> {{ $persona->rut }}</span>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $persona->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $persona->active ? 'PERSONA ACTIVA' : 'PERSONA INACTIVA' }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            <strong class="font-medium text-gray-700">Cuenta:</strong>
                            @if ($persona->user)
                                {{ $persona->user->email }}
                                <span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $persona->user->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $persona->user->active ? 'Activa' : 'Inactiva' }}</span>
                            @else
                                Sin cuenta asociada
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.dotacion.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">← Volver a Dotación</a>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Dotación actual</h2>
                @if ($vinculosActuales->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">Sin vínculos vigentes.</div>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($vinculosActuales as $vinculo)
                            @php($estadoVinculo = $vinculo->estadoEn(today())->value)
                            <article class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                                <header class="flex items-start justify-between gap-3 border-b border-indigo-100 pb-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate font-semibold text-gray-900">{{ $estructura->ruta($vinculo->unidad) }}</h3>
                                        @if ($vinculo->esGeneradoPorTramite())
                                            <p class="mt-1 text-xs font-medium text-indigo-700">Generado por trámite @can('view', $vinculo->tramiteOrigen)<a class="underline hover:text-indigo-900" href="{{ route('reemplazos.show', $vinculo->tramiteOrigen) }}">{{ $vinculo->tramiteOrigen->codigo }}</a>@else{{ $vinculo->tramiteOrigen->codigo }}@endcan</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $estadoVinculo === 'VIGENTE' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">{{ $estadoVinculo }}</span>
                                </header>
                                <dl class="mt-3 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Cargo/función</dt><dd class="mt-1 text-gray-800">{{ $vinculo->cargo_funcion }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Calidad contractual</dt><dd class="mt-1 text-gray-800">{{ $vinculo->calidadContractual->nombre }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estamento</dt><dd class="mt-1 text-gray-800">{{ $vinculo->estamento->nombre }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Profesión</dt><dd class="mt-1 text-gray-800">{{ $vinculo->profesion?->nombre ?? '-' }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Grado EUS</dt><dd class="mt-1 text-gray-800">{{ $vinculo->grado_eus ?? '-' }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Período</dt><dd class="mt-1 text-gray-800">{{ $vinculo->vigente_desde->format('d/m/Y') }} → {{ $vinculo->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</dd></div>
                                </dl>
                                @can('update', $vinculo)
                                    <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-indigo-100 pt-3 text-sm">
                                        <a class="font-medium text-indigo-700 hover:underline" href="{{ route('admin.dotacion.edit', $vinculo) }}">Editar</a>
                                        @if ($vinculo->vigente_hasta === null)
                                            <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar este vínculo laboral?')">
                                                @csrf @method('PATCH')
                                                <label class="text-xs text-gray-600">Fecha de término<input type="date" name="vigente_hasta" min="{{ $vinculo->vigente_desde->toDateString() }}" required class="mt-1 block w-36 rounded-md border-gray-300 text-xs"></label>
                                                <button class="font-medium text-indigo-700 hover:underline">Cerrar vínculo</button>
                                            </form>
                                        @endif
                                    </div>
                                @endcan
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Historial de dotación</h2>
                <div class="divide-y divide-gray-200 rounded-lg border border-gray-200">
                    @forelse ($vinculosHistoricos as $vinculo)
                        <article class="p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold text-gray-900">{{ $estructura->ruta($vinculo->unidad) }}</h3>
                                        <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700">FINALIZADO</span>
                                    </div>
                                    @if ($vinculo->esGeneradoPorTramite())
                                        <p class="mt-1 text-xs font-medium text-indigo-700">Generado por trámite @can('view', $vinculo->tramiteOrigen)<a class="underline hover:text-indigo-900" href="{{ route('reemplazos.show', $vinculo->tramiteOrigen) }}">{{ $vinculo->tramiteOrigen->codigo }}</a>@else{{ $vinculo->tramiteOrigen->codigo }}@endcan</p>
                                    @endif
                                </div>
                                <p class="shrink-0 text-sm font-medium text-gray-700">{{ $vinculo->vigente_desde->format('d/m/Y') }} → {{ $vinculo->vigente_hasta?->format('d/m/Y') ?? '-' }}</p>
                            </div>
                            <dl class="mt-3 grid gap-x-5 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-5">
                                <div><dt class="text-xs text-gray-500">Cargo/función</dt><dd class="mt-0.5 text-gray-800">{{ $vinculo->cargo_funcion }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Calidad contractual</dt><dd class="mt-0.5 text-gray-800">{{ $vinculo->calidadContractual->nombre }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Estamento</dt><dd class="mt-0.5 text-gray-800">{{ $vinculo->estamento->nombre }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Profesión</dt><dd class="mt-0.5 text-gray-800">{{ $vinculo->profesion?->nombre ?? '-' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Grado EUS</dt><dd class="mt-0.5 text-gray-800">{{ $vinculo->grado_eus ?? '-' }}</dd></div>
                            </dl>
                            @can('update', $vinculo)
                                <div class="mt-3 border-t border-gray-100 pt-3 text-sm"><a class="font-medium text-indigo-700 hover:underline" href="{{ route('admin.dotacion.edit', $vinculo) }}">Editar</a></div>
                            @endcan
                        </article>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-600">Sin vínculos históricos.</p>
                    @endforelse
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-base font-semibold text-gray-900">Responsabilidades institucionales</h2>
                    <div class="divide-y divide-gray-100">
                        @forelse ($persona->responsabilidades as $responsabilidad)
                            <div class="py-3 first:pt-0 last:pb-0"><p class="text-sm font-medium text-gray-800">{{ $estructura->ruta($responsabilidad->unidad) }}</p><p class="mt-1 text-xs text-gray-600">{{ $responsabilidad->tipo->value }} · {{ $responsabilidad->vigente_desde->format('d/m/Y') }} → {{ $responsabilidad->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</p></div>
                        @empty
                            <p class="py-4 text-sm text-gray-600">Sin responsabilidades.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-base font-semibold text-gray-900">Accesos operativos</h2>
                    <div class="divide-y divide-gray-100">
                        @forelse ($persona->user?->accesosOperativos ?? [] as $acceso)
                            <div class="py-3 first:pt-0 last:pb-0"><p class="text-sm font-medium text-gray-800">{{ $estructura->ruta($acceso->unidad) }}</p><p class="mt-1 text-xs text-gray-600">{{ $acceso->alcance->etiqueta() }} · {{ $acceso->vigente_desde->format('d/m/Y') }} → {{ $acceso->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</p></div>
                        @empty
                            <p class="py-4 text-sm text-gray-600">Sin accesos operativos.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
