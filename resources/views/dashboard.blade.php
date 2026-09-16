<x-app-layout>
    @if ($esAdministrador)
        @php
            $gruposAdministracion = [
                'Personas y acceso' => [
                    ['nombre' => 'Personas', 'descripcion' => 'Registro y datos de personas.', 'ruta' => 'admin.personas.index', 'permiso' => 'personas.ver'],
                    ['nombre' => 'Usuarios', 'descripcion' => 'Cuentas, roles y estado de acceso.', 'ruta' => 'admin.usuarios.index', 'permiso' => 'admin.usuarios'],
                    ['nombre' => 'Acceso por unidades', 'descripcion' => 'Alcance operativo de cada usuario.', 'ruta' => 'admin.accesos.index', 'permiso' => 'accesos_operativos.ver'],
                    ['nombre' => 'Roles y permisos', 'descripcion' => 'Perfiles y capacidades del sistema.', 'ruta' => 'admin.roles-permisos.index', 'permiso' => 'admin.roles_permisos'],
                ],
                'Organización' => [
                    ['nombre' => 'Dotación', 'descripcion' => 'Vínculos laborales por unidad.', 'ruta' => 'admin.dotacion.index', 'permiso' => 'dotacion.ver'],
                    ['nombre' => 'Estructura organizacional', 'descripcion' => 'Unidades y dependencias.', 'ruta' => 'admin.estructura.index', 'permiso' => 'estructura_organizacional.ver'],
                    ['nombre' => 'Organigrama', 'descripcion' => 'Vista jerárquica de la organización.', 'ruta' => 'admin.estructura.organigrama', 'permiso' => 'estructura_organizacional.ver'],
                    ['nombre' => 'Responsables institucionales', 'descripcion' => 'Titulares y subrogantes por unidad.', 'ruta' => 'admin.responsabilidades.index', 'permiso' => 'responsabilidades.ver'],
                ],
                'Configuración' => [
                    ['nombre' => 'Calidades contractuales', 'descripcion' => 'Modalidades utilizadas en dotación.', 'ruta' => 'admin.calidades.index', 'permiso' => 'calidades_contractuales.ver'],
                    ['nombre' => 'Tipos organizacionales', 'descripcion' => 'Tipos disponibles para las unidades.', 'ruta' => 'admin.tipos-organizacionales.index', 'permiso' => 'tipos_unidad_organizacional.gestionar'],
                ],
            ];
        @endphp

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-7 px-4 sm:px-6 lg:px-8">
                <section>
                    <h1 class="text-xl font-semibold text-gray-900">Bienvenido/a, {{ auth()->user()->name }}</h1>
                    <p class="mt-1 text-sm text-gray-600">Panel general de administración y configuración del sistema.</p>
                </section>

                @include('dashboard.partials.mis-tramites')

                <section aria-labelledby="resumen-title">
                    <h3 id="resumen-title" class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Resumen</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @can('personas.ver')
                            <a href="{{ route('admin.personas.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow"><p class="text-sm font-medium text-gray-600">Personas</p><div class="mt-2 flex items-end justify-between gap-3"><strong class="text-2xl font-semibold text-gray-900">{{ $resumen['personas']['total'] }}</strong><span class="text-xs text-gray-500">{{ $resumen['personas']['activas'] }} activas</span></div></a>
                        @endcan
                        @can('admin.usuarios')
                            <a href="{{ route('admin.usuarios.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow"><p class="text-sm font-medium text-gray-600">Usuarios</p><div class="mt-2 flex items-end justify-between gap-3"><strong class="text-2xl font-semibold text-gray-900">{{ $resumen['usuarios']['total'] }}</strong><span class="text-xs text-gray-500">{{ $resumen['usuarios']['activos'] }} activos</span></div></a>
                        @endcan
                        @can('estructura_organizacional.ver')
                            <a href="{{ route('admin.estructura.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow"><p class="text-sm font-medium text-gray-600">Unidades organizacionales</p><div class="mt-2 flex items-end justify-between gap-3"><strong class="text-2xl font-semibold text-gray-900">{{ $resumen['unidades']['total'] }}</strong><span class="text-xs text-gray-500">{{ $resumen['unidades']['activas'] }} activas</span></div></a>
                        @endcan
                        @can('admin.roles_permisos')
                            <a href="{{ route('admin.roles-permisos.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow"><p class="text-sm font-medium text-gray-600">Roles</p><div class="mt-2 flex items-end justify-between gap-3"><strong class="text-2xl font-semibold text-gray-900">{{ $resumen['roles']['total'] }}</strong><span class="text-xs text-gray-500">configurados</span></div></a>
                        @endcan
                    </div>
                </section>

                <section aria-labelledby="administracion-title" class="space-y-5">
                    <div>
                        <h3 id="administracion-title" class="text-lg font-semibold text-gray-900">Administración rápida</h3>
                        <p class="mt-0.5 text-sm text-gray-500">Accede a las tareas frecuentes de configuración.</p>
                    </div>

                    @foreach ($gruposAdministracion as $tituloGrupo => $modulos)
                        @php($modulosVisibles = collect($modulos)->filter(fn ($modulo) => auth()->user()->can($modulo['permiso'])))
                        @if ($modulosVisibles->isNotEmpty())
                            <div>
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $tituloGrupo }}</h4>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach ($modulosVisibles as $modulo)
                                        <a href="{{ route($modulo['ruta']) }}" class="group flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/30">
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-gray-900 group-hover:text-indigo-800">{{ $modulo['nombre'] }}</span>
                                                <span class="mt-0.5 block text-xs text-gray-500">{{ $modulo['descripcion'] }}</span>
                                            </span>
                                            <span class="shrink-0 text-indigo-500" aria-hidden="true">→</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>

                @can('admin.usuarios')
                    <section aria-labelledby="atencion-title">
                        <div class="mb-3">
                            <h3 id="atencion-title" class="text-lg font-semibold text-gray-900">Requiere atención</h3>
                            <p class="mt-0.5 text-sm text-gray-500">Situaciones objetivas que conviene revisar.</p>
                        </div>

                        @if ($usuariosActivosSinRoles > 0)
                            <a href="{{ route('admin.usuarios.index') }}" class="flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 transition hover:bg-amber-100/70 sm:px-5">
                                <span>
                                    <span class="block text-sm font-semibold text-amber-900">Usuarios activos sin roles</span>
                                    <span class="mt-0.5 block text-xs text-amber-800">Estas cuentas están activas, pero no tienen un rol asignado.</span>
                                </span>
                                <span class="inline-flex shrink-0 items-center gap-2"><strong class="rounded-full bg-white px-2.5 py-1 text-sm text-amber-900">{{ $usuariosActivosSinRoles }}</strong><span class="text-amber-800" aria-hidden="true">→</span></span>
                            </a>
                        @else
                            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">No hay usuarios activos sin roles.</div>
                        @endif
                    </section>
                @endcan
            </div>
        </div>
    @else
        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                @if ($esGestionPersonas)
                    @include('dashboard.partials.gestion-personas')
                @else
                <section class="rounded-xl border border-indigo-100 bg-white p-5 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">Hola, {{ auth()->user()->name }}</h1>
                        <p class="mt-1 text-sm text-slate-600">Gestiona y realiza seguimiento a tus solicitudes.</p>
                    </div>
                    @can('reemplazos.crear')
                        <a href="{{ route('reemplazos.create') }}" class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0">+ Nueva solicitud</a>
                    @endcan
                </section>
                @can('tramites.ver_propios')
                    <section aria-label="Indicadores de trámites" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Requieren atención</p><p class="mt-1 text-2xl font-semibold text-amber-950">{{ $resumenMisTramites['requieren_atencion'] }}</p></div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">En tramitación</p><p class="mt-1 text-2xl font-semibold text-blue-950">{{ $resumenMisTramites['en_tramitacion'] }}</p></div>
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abiertos</p><p class="mt-1 text-2xl font-semibold text-slate-900">{{ $resumenMisTramites['abiertos'] }}</p></div>
                    </section>

                    @if($tramitesRequierenAtencion->isNotEmpty())
                        <section aria-labelledby="requieren-atencion-title">
                            <div class="mb-3"><h3 id="requieren-atencion-title" class="text-lg font-semibold text-gray-900">Requieren mi atención</h3><p class="mt-0.5 text-sm text-gray-500">Borradores y solicitudes devueltas que puedes continuar.</p></div>
                            <div class="grid gap-3 lg:grid-cols-2">
                                @foreach($tramitesRequierenAtencion as $tramite)
                                    <article class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="whitespace-nowrap font-mono text-sm font-semibold text-gray-900">{{ $tramite->codigo }}</span><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $tramite->estadoTramite->nombre }}</span></div><p class="mt-2 text-sm font-medium text-gray-800">{{ $tramite->tipoTramite->nombre }}</p><p class="mt-0.5 truncate text-xs text-gray-500">{{ $tramite->unidadOrganizacional->nombre }}</p></div>
                                        <a href="{{ route('reemplazos.edit', $tramite) }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">Continuar <span class="ml-1" aria-hidden="true">→</span></a>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endcan
                @include('dashboard.partials.mis-tramites')
                @endif
            </div>
        </div>
    @endif
</x-app-layout>
