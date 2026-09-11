<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Ficha de Persona</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <p class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />

        <section class="rounded-xl bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Datos de Persona</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">
                        {{ trim($persona->nombres.' '.($persona->apellido_paterno ?? '').' '.($persona->apellido_materno ?? '')) }}
                    </h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                        <span><strong class="font-medium text-gray-700">RUT:</strong> {{ $persona->rut }}</span>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $persona->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $persona->active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                @can('personas.gestionar')
                    <a href="{{ route('admin.personas.edit', $persona) }}" class="text-sm text-indigo-700 hover:underline">Editar Persona</a>
                    <form method="POST" action="{{ route('admin.personas.activo', $persona) }}" class="inline" onsubmit="return confirm('{{ $persona->active ? '¿Confirma que desea inactivar esta persona?' : '¿Confirma que desea reactivar esta persona?' }}')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="text-sm text-indigo-700 hover:underline">{{ $persona->active ? 'Inactivar' : 'Reactivar' }}</button>
                    </form>
                @endcan
                <a href="{{ route('admin.personas.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver al listado</a>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-800">Acceso al sistema</h2>

            @if ($persona->user)
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Usuario</dt>
                        <dd class="mt-1 text-gray-800">{{ $persona->user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estado</dt>
                        <dd class="mt-1 text-gray-800">{{ $persona->user->active ? 'Activo' : 'Inactivo' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Roles</dt>
                        <dd class="mt-1 text-gray-800">{{ $persona->user->roles->pluck('name')->join(', ') ?: 'Sin roles asignados' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                    @can('admin.usuarios')
                        <a href="{{ route('admin.usuarios.index', ['user_id' => $persona->user->id]).'#usuario-'.$persona->user->id }}" class="text-sm text-indigo-700 hover:underline">Administrar roles</a>
                    @endcan
                    @can('accesos_operativos.ver')
                        <a href="{{ route('admin.accesos.index', ['usuario' => $persona->user->email]) }}" class="text-sm text-indigo-700 hover:underline">Accesos operativos</a>
                    @endcan
                </div>
            @else
                <p class="mt-3 text-sm text-gray-600"><strong class="font-medium text-gray-700">Estado:</strong> Sin cuenta de usuario</p>
                @can('admin.usuarios')
                    <a href="{{ route('admin.usuarios.create-for-persona', $persona) }}" class="mt-4 inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Crear acceso al sistema</a>
                @endcan
            @endif
        </section>

        @if ($verDotacion)
            <section class="rounded-xl bg-white p-5 shadow-sm">
                @php
                    $vinculosVigentes = $vinculos->filter(fn ($vinculo) => $vinculo->estadoEn(now())->name === 'VIGENTE');
                    $vinculosHistoricos = $vinculos->filter(fn ($vinculo) => $vinculo->estadoEn(now())->name === 'FINALIZADO');
                @endphp

                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-800">Dotación</h2>
                    @if ($puedeAgregarVinculo)
                    <a href="{{ route('admin.dotacion.create', ['persona_id' => $persona->id]) }}" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Agregar vínculo de dotación</a>
                    @endif
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Vínculos vigentes</h3>
                    @if ($vinculosVigentes->isEmpty())
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-600">
                            No existen vínculos de dotación vigentes.
                        </div>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($vinculosVigentes as $vinculo)
                            <article class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-5 shadow-sm">
                                <div class="mb-4 flex items-start justify-between gap-3 border-b border-indigo-100 pb-3">
                                    <h4 class="text-base font-semibold text-gray-900">{{ $vinculo->unidad->nombre ?? '-' }}</h4>
                                    <span class="inline-flex shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">VIGENTE</span>
                                </div>
                                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Cargo/función</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->cargo_funcion ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Calidad contractual</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->calidadContractual->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Profesión</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->profesion->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estamento</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->estamento->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Grado EUS</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->grado_eus ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Vigencia</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->vigente_desde->format('d/m/Y') }} → Actualidad</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Origen</dt>
                                        <dd class="mt-1 text-gray-800">{{ $vinculo->origen->name ?? '-' }}</dd>
                                    </div>
                                </dl>

                                @can('dotacion.gestionar')
                                @can('update', $vinculo)
                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <a href="{{ route('admin.dotacion.edit', $vinculo) }}" class="text-indigo-700 hover:underline">Editar</a>
                                    @if ($vinculo->vigente_hasta === null)
                                        <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar este vínculo laboral?')">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-xs text-gray-600" for="vigente_hasta_{{ $vinculo->id }}">Fecha de término</label>
                                            <input type="date" name="vigente_hasta" id="vigente_hasta_{{ $vinculo->id }}" min="{{ $vinculo->vigente_desde->toDateString() }}" value="{{ old('vigente_hasta', $vinculo->vigente_hasta?->toDateString()) }}" class="w-40 rounded border-gray-300 text-sm" required>
                                            <button type="submit" class="text-indigo-700 hover:underline">Cerrar vínculo</button>
                                        </form>
                                    @endif
                                </div>
                                @endcan
                                @endcan
                            </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-7 border-t border-gray-100 pt-5">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600">Historial de dotación</h3>
                    @if ($vinculosHistoricos->isNotEmpty())
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($vinculosHistoricos as $vinculo)
                            <article class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                                <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-200 pb-3">
                                    <h4 class="font-semibold text-gray-800">{{ $vinculo->unidad->nombre ?? '-' }}</h4>
                                    <span class="inline-flex shrink-0 rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700">FINALIZADO</span>
                                </div>
                                <dl class="grid gap-x-4 gap-y-2.5 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Cargo/función</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->cargo_funcion ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Calidad contractual</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->calidadContractual->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Profesión</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->profesion->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estamento</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->estamento->nombre ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Grado EUS</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->grado_eus ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Vigencia</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->vigente_desde->format('d/m/Y') }} → {{ $vinculo->vigente_hasta?->format('d/m/Y') ?? '-' }}</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Origen</dt>
                                        <dd class="mt-1 text-gray-700">{{ $vinculo->origen->name ?? '-' }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-3 border-t border-gray-200 pt-3">
                                    @can('dotacion.gestionar')
                                    @can('update', $vinculo)
                                    <a href="{{ route('admin.dotacion.edit', $vinculo) }}" class="text-indigo-700 hover:underline">Editar</a>
                                    @endcan
                                    @endcan
                                </div>
                            </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif
        </div>
    </div>
</x-app-layout>
