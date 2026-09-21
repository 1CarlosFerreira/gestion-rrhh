@php
    $descripcionesRoles = [
        'Administrador' => 'Administración completa del sistema.',
        'Gestión de Personas' => 'Gestión institucional de RRHH y trámites.',
        'Solicitante' => 'Creación y seguimiento de trámites autorizados.',
        'Funcionario' => 'Acceso personal según funcionalidades habilitadas.',
    ];
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Configurar perfil de acceso</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6">
            <x-admin-process-steps current="acceso" />

            @if ($usuarioCreado)
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-900">✓ Usuario creado correctamente</div>
            @elseif (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs font-medium text-gray-500">Persona</dt><dd class="mt-1 font-medium text-gray-900">{{ $user->persona->nombre_completo }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Correo</dt><dd class="mt-1 text-gray-900">{{ $user->email }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Estado</dt><dd class="mt-1"><span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-800">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></dd></div>
                </dl>
            </section>

            <form method="POST" action="{{ route('admin.usuarios.roles.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="finalizar_perfil" value="1">

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <fieldset>
                        <legend class="text-base font-semibold text-gray-900">Rol del sistema</legend>
                        <p class="mt-1 text-sm text-gray-500">Define qué acciones puede realizar esta persona.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($roles as $role)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, old('roles', $user->getRoleNames()->all()), true)) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <span class="block font-medium text-gray-900">{{ $role->name }}</span>
                                        @if (isset($descripcionesRoles[$role->name]))
                                            <span class="mt-0.5 block text-xs leading-5 text-gray-500">{{ $descripcionesRoles[$role->name] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('roles')" class="mt-2" />
                        <x-input-error :messages="$errors->get('roles.*')" class="mt-2" />
                    </fieldset>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-900">Alcance actual</h2>
                    <p class="mt-1 text-sm text-gray-500">El rol define las acciones disponibles; el alcance determina las unidades sobre las que puede operar.</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($responsabilidades as $responsabilidad)
                            <article class="rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                                <p class="font-semibold text-green-900">✓ {{ $responsabilidad->tipo->etiqueta() }}</p>
                                <p class="mt-1 text-sm text-green-950">{{ $responsabilidad->unidad->nombre }}</p>
                                <p class="mt-1 text-xs text-green-800">Aportado por responsabilidad institucional.</p>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">Sin alcance vigente aportado por responsabilidades institucionales.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-5">
                    <h2 class="text-sm font-semibold text-gray-800">Unidades autorizadas</h2>
                    <p class="mt-1 text-sm text-gray-500">Configuración avanzada e independiente de las responsabilidades institucionales.</p>
                    <ul class="mt-4 space-y-2 text-sm text-gray-700">
                        @forelse ($accesosOperativos as $acceso)
                            <li class="rounded-lg border border-gray-200 bg-white p-3">{{ $acceso->unidad->nombre }} · {{ $acceso->alcance->etiqueta() }} · {{ $acceso->vigente_desde->format('d/m/Y') }} → {{ $acceso->vigente_hasta?->format('d/m/Y') ?? 'Sin término' }}</li>
                        @empty
                            <li class="text-gray-500">Sin accesos operativos vigentes.</li>
                        @endforelse
                    </ul>

                    @can('create', App\Models\UserUnidadAcceso::class)
                        <div class="mt-4">
                            <a href="{{ route('admin.accesos.create', ['user_id' => $user->id]) }}" class="text-sm font-medium text-indigo-700 hover:underline">+ Agregar unidad autorizada</a>
                        </div>
                    @endcan
                </section>

                <div class="flex justify-end border-t border-gray-200 pt-5">
                    <x-primary-button>Guardar roles y finalizar</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
