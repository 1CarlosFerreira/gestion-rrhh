@php
    $vinculosFuturos = $vinculos->filter(fn ($vinculo) => $vinculo->estadoEn(today())->value === 'FUTURO');
    $vinculosVigentes = $vinculos->filter(fn ($vinculo) => $vinculo->estadoEn(today())->value === 'VIGENTE');
    $vinculosHistoricos = $vinculos->filter(fn ($vinculo) => $vinculo->estadoEn(today())->value === 'FINALIZADO');

    $responsabilidades = $persona->relationLoaded('responsabilidades') ? $persona->responsabilidades : collect();
    $responsabilidadesFuturas = $responsabilidades->filter(fn ($item) => $item->vigente_desde->gt(today()));
    $responsabilidadesVigentes = $responsabilidades->filter(fn ($item) => $item->estaVigenteEn(today()));
    $responsabilidadesHistoricas = $responsabilidades->filter(fn ($item) => $item->vigente_hasta?->lt(today()) === true);

    $accesos = $persona->user?->relationLoaded('accesosOperativos') ? $persona->user->accesosOperativos : collect();
    $accesosFuturos = $accesos->filter(fn ($item) => $item->vigente_desde->gt(today()));
    $accesosVigentes = $accesos->filter(fn ($item) => $item->estaVigenteEn(today()));
    $accesosHistoricos = $accesos->filter(fn ($item) => $item->vigente_hasta?->lt(today()) === true);
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">Ficha de Persona</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-admin-process-steps current="finalizar" />

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @if ($errors->has('responsabilidad_incompatible'))
                        <a href="{{ route('admin.responsabilidades.index', ['persona' => $persona->rut]) }}" class="mt-3 inline-flex font-medium text-red-800 underline">Administrar responsabilidad afectada</a>
                    @endif
                </div>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Datos personales</p>
                        <h2 class="mt-1 text-xl font-semibold text-gray-900">{{ $persona->nombre_completo }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                            <span>RUT {{ $persona->rut }}</span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $persona->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $persona->active ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @can('personas.gestionar')
                            <a href="{{ route('admin.personas.edit', $persona) }}" class="text-sm font-medium text-indigo-700 hover:underline">Editar Persona</a>
                            <form method="POST" action="{{ route('admin.personas.activo', $persona) }}" onsubmit="return confirm('{{ $persona->active ? '¿Confirma que desea inactivar esta persona?' : '¿Confirma que desea reactivar esta persona?' }}')">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline">{{ $persona->active ? 'Inactivar' : 'Reactivar' }}</button>
                            </form>
                        @endcan
                        <a href="{{ route('admin.personas.index') }}" class="text-sm text-gray-600 hover:text-gray-900 hover:underline">Volver al listado</a>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Acceso al sistema</h2>
                        <p class="mt-1 text-sm text-gray-500">Cuenta, roles y unidades sobre las que puede operar.</p>
                    </div>
                    @if ($persona->user && auth()->user()->can('admin.usuarios') && auth()->user()->hasRole('Administrador'))
                        <a href="{{ route('admin.usuarios.perfil-acceso.edit', $persona->user) }}" class="inline-flex items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Administrar acceso</a>
                    @endif
                </div>

                @if ($persona->user)
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs font-medium text-gray-500">Usuario</dt><dd class="mt-1 text-gray-900">{{ $persona->user->email }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ $persona->user->active ? 'Activo' : 'Inactivo' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Roles</dt><dd class="mt-1 text-gray-900">{{ $persona->user->roles->pluck('name')->join(', ') ?: 'Sin roles asignados' }}</dd></div>
                    </dl>

                    @can('admin.usuarios')
                        @if (! $persona->user->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.usuarios.activo', $persona->user) }}" class="mt-4">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline">{{ $persona->user->active ? 'Desactivar usuario' : 'Activar usuario' }}</button>
                            </form>
                        @endif
                    @endcan

                    <div class="mt-6 border-t border-gray-100 pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Ámbito de operación</h3>
                                <p class="mt-1 text-xs text-gray-500">Estas unidades determinan dónde puede operar este usuario cuando sus roles y permisos lo permitan.</p>
                            </div>
                            @can('create', App\Models\UserUnidadAcceso::class)
                                <a href="{{ route('admin.accesos.create', ['user_id' => $persona->user->id, 'return_to' => 'persona']) }}" class="text-sm font-medium text-indigo-700 hover:underline">+ Agregar autorización adicional</a>
                            @endcan
                        </div>

                        @if ($ambitoOperacion->isEmpty())
                            <p class="mt-4 text-sm text-gray-500">Sin unidades vigentes en el ámbito de operación.</p>
                        @else
                            <div class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
                                @foreach ($ambitoOperacion as $origenes)
                                    <article class="p-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $origenes['unidad']->nombre }}</p>
                                        <ul class="mt-1 space-y-1 text-xs text-gray-500">
                                            @foreach ($origenes['responsabilidades'] as $responsabilidad)
                                                <li>{{ $responsabilidad->tipo->etiqueta() }}</li>
                                            @endforeach
                                            @foreach ($origenes['accesos'] as $acceso)
                                                <li class="flex flex-wrap items-center justify-between gap-2">
                                                    <span>Autorización adicional · {{ $acceso->alcance->etiqueta() }}</span>
                                                    @can('update', $acceso)
                                                        <span class="flex items-center gap-3">
                                                            <a href="{{ route('admin.accesos.edit', ['acceso' => $acceso, 'return_to' => 'persona']) }}" class="font-medium text-indigo-700 hover:underline">Editar</a>
                                                            @if ($acceso->vigente_hasta === null)
                                                                <form method="POST" action="{{ route('admin.accesos.close', $acceso) }}" class="flex items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar esta autorización adicional?')">
                                                                    @csrf @method('PATCH')
                                                                    <input type="hidden" name="return_to" value="persona">
                                                                    <label>Término<input type="date" name="vigente_hasta" min="{{ $acceso->vigente_desde->toDateString() }}" required class="ml-1 w-32 rounded-md border-gray-300 py-1 text-xs"></label>
                                                                    <button type="submit" class="font-medium text-red-700 hover:underline">Cerrar</button>
                                                                </form>
                                                            @endif
                                                        </span>
                                                    @endcan
                                                </li>
                                            @endforeach
                                        </ul>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @can('accesos_operativos.ver')
                            @foreach (['Autorizaciones futuras' => $accesosFuturos, 'Historial de autorizaciones' => $accesosHistoricos] as $titulo => $grupo)
                                @if ($grupo->isNotEmpty())
                                    <div class="mt-4">
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $titulo }}</h4>
                                        <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                            @foreach ($grupo as $acceso)
                                                <li>{{ $acceso->unidad->nombre }} · Autorización adicional · {{ $acceso->alcance->etiqueta() }} · {{ $acceso->vigente_desde->format('d/m/Y') }} → {{ $acceso->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        @endcan
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-600">Sin cuenta de usuario.</p>
                    @can('admin.usuarios')
                        <a href="{{ route('admin.usuarios.create-for-persona', $persona) }}" class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Crear acceso al sistema</a>
                    @endcan
                @endif
            </section>

            @if ($verDotacion)
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-gray-900">Dotación</h2><p class="mt-1 text-sm text-gray-500">Vínculos laborales de la Persona.</p></div>
                        @if ($puedeAgregarVinculo)
                            <a href="{{ route('admin.dotacion.create', ['persona_id' => $persona->id]) }}" class="text-sm font-medium text-indigo-700 hover:underline">+ Agregar a dotación</a>
                        @endif
                    </div>

                    @foreach (['Vínculos futuros' => $vinculosFuturos, 'Vínculos vigentes' => $vinculosVigentes, 'Historial de vínculos' => $vinculosHistoricos] as $titulo => $grupo)
                        <div class="mt-5">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $titulo }}</h3>
                            @if ($grupo->isEmpty())
                                <p class="mt-2 text-sm text-gray-500">Sin registros.</p>
                            @else
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    @foreach ($grupo as $vinculo)
                                        <article class="rounded-lg border border-gray-200 p-4">
                                            <header class="flex items-start justify-between gap-3">
                                                <h4 class="font-semibold text-gray-900">{{ $vinculo->unidad->nombre }}</h4>
                                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $vinculo->estadoEn(today())->value === 'VIGENTE' ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $vinculo->estadoEn(today())->value }}</span>
                                            </header>
                                            <p class="mt-3 text-sm font-medium text-gray-800">{{ $vinculo->cargo_funcion }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $vinculo->estamento->nombre }} · {{ $vinculo->profesion?->nombre ?? 'Sin profesión' }}</p>
                                            <p class="text-xs text-gray-500">{{ $vinculo->calidadContractual->nombre }}@if($vinculo->grado_eus) · Grado {{ $vinculo->grado_eus }}@endif</p>
                                            <p class="mt-2 text-sm text-gray-700">{{ $vinculo->vigente_desde->format('d/m/Y') }} → {{ $vinculo->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</p>
                                            @if ($vinculo->esGeneradoPorTramite())
                                                <p class="mt-2 text-xs font-medium text-indigo-700">Generado por trámite {{ $vinculo->tramiteOrigen->codigo }}</p>
                                            @endif

                                            @can('update', $vinculo)
                                                <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-3">
                                                    <a href="{{ route('admin.dotacion.edit', ['vinculo' => $vinculo, 'return_to' => 'persona']) }}" class="text-sm font-medium text-indigo-700 hover:underline">Editar</a>
                                                    @if ($vinculo->vigente_hasta === null)
                                                        <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar este vínculo laboral?')">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="return_to" value="persona">
                                                            <label class="text-xs text-gray-500">Término<input type="date" name="vigente_hasta" min="{{ $vinculo->vigente_desde->toDateString() }}" required class="mt-1 block w-32 rounded-md border-gray-300 py-1.5 text-xs"></label>
                                                            <button type="submit" class="pb-1 text-sm font-medium text-red-700 hover:underline">Cerrar</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endcan
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            @can('responsabilidades.ver')
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-gray-900">Responsabilidades institucionales</h2><p class="mt-1 text-sm text-gray-500">Titularidades y subrogancias, independientes de los vínculos laborales.</p></div>
                        @can('create', App\Models\UnidadResponsable::class)
                            <a href="{{ route('admin.responsabilidades.create', ['persona_id' => $persona->id, 'return_to' => 'persona']) }}" class="text-sm font-medium text-indigo-700 hover:underline">+ Agregar responsabilidad</a>
                        @endcan
                    </div>

                    @foreach (['Vigentes' => $responsabilidadesVigentes, 'Futuras' => $responsabilidadesFuturas, 'Históricas' => $responsabilidadesHistoricas] as $titulo => $grupo)
                        <div class="mt-5">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $titulo }}</h3>
                            @if ($grupo->isEmpty())
                                <p class="mt-2 text-sm text-gray-500">Sin registros.</p>
                            @else
                                <div class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                                    @foreach ($grupo as $responsabilidad)
                                        <article class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $responsabilidad->tipo->etiqueta() }} · {{ $responsabilidad->unidad->nombre }}</p>
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $responsabilidad->estaVigenteEn(today()) ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $responsabilidad->vigente_desde->gt(today()) ? 'FUTURA' : ($responsabilidad->estaVigenteEn(today()) ? 'VIGENTE' : 'FINALIZADA') }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">{{ $responsabilidad->vigente_desde->format('d/m/Y') }} → {{ $responsabilidad->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }} · {{ $responsabilidad->puede_aprobar ? 'Puede aprobar' : 'Sin aprobación' }}</p>
                                            </div>
                                            @can('update', $responsabilidad)
                                                <div class="flex flex-wrap items-end gap-3">
                                                    <a href="{{ route('admin.responsabilidades.edit', ['responsabilidad' => $responsabilidad, 'return_to' => 'persona']) }}" class="text-sm font-medium text-indigo-700 hover:underline">Editar</a>
                                                    @if ($responsabilidad->vigente_hasta === null)
                                                        <form method="POST" action="{{ route('admin.responsabilidades.close', $responsabilidad) }}" class="flex items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar esta responsabilidad?')">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="return_to" value="persona">
                                                            <label class="text-xs text-gray-500">Término<input type="date" name="vigente_hasta" min="{{ $responsabilidad->vigente_desde->toDateString() }}" required class="mt-1 block w-32 rounded-md border-gray-300 py-1.5 text-xs"></label>
                                                            <button type="submit" class="pb-1 text-sm font-medium text-red-700 hover:underline">Cerrar</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endcan
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endcan
        </div>
    </div>
</x-app-layout>
