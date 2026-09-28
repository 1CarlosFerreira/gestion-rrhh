@php
    $descripcionesRoles = [
        'Administrador' => 'Administración completa del sistema.',
        'Gestión de Personas' => 'Gestión institucional de RRHH y trámites.',
        'Solicitante' => 'Creación y seguimiento de trámites autorizados.',
        'Jefatura' => 'Consulta básica dentro de su ámbito de operación.',
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
                <h2 class="text-base font-semibold text-gray-900">Cuenta</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs font-medium text-gray-500">Persona</dt><dd class="mt-1 font-medium text-gray-900">{{ $user->persona->nombre_completo }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Correo de acceso</dt><dd class="mt-1 text-gray-900">{{ $user->email }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Estado</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $user->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></dd></div>
                </dl>
            </section>

            <details class="group rounded-xl border border-gray-200 bg-white shadow-sm" @if ($errors->hasAny(['email', 'password', 'password_confirmation'])) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 sm:p-6 [&::-webkit-details-marker]:hidden">
                    <span>
                        <span class="block text-base font-semibold text-gray-900">Credenciales</span>
                        <span class="mt-1 block text-sm text-gray-500">Cambiar correo o restablecer contraseña</span>
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200 group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </summary>

                <div class="grid gap-5 border-t border-gray-100 px-5 pb-5 pt-5 sm:px-6 sm:pb-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('admin.usuarios.email.update', $user) }}" class="rounded-lg border border-gray-200 p-4">
                        @csrf
                        @method('PATCH')
                        <h3 class="text-sm font-semibold text-gray-900">Cambiar correo</h3>
                        <div class="mt-3">
                            <x-input-label for="email" value="Correo de acceso" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" autocomplete="email" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <x-primary-button class="mt-4">Actualizar correo</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('admin.usuarios.password.update', $user) }}" class="rounded-lg border border-gray-200 p-4">
                        @csrf
                        @method('PATCH')
                        <h3 class="text-sm font-semibold text-gray-900">Restablecer contraseña</h3>
                        <p class="mt-1 text-xs text-gray-500">Establece una nueva contraseña. La contraseña actual nunca se muestra ni puede recuperarse.</p>
                        <div class="mt-3">
                            <x-input-label for="password" value="Nueva contraseña" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div class="mt-3">
                            <x-input-label for="password_confirmation" value="Confirmar nueva contraseña" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
                        </div>
                        <x-primary-button class="mt-4">Restablecer contraseña</x-primary-button>
                    </form>
                </div>
            </details>

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
                    <h2 class="text-sm font-semibold text-gray-800">Autorizaciones adicionales</h2>
                    <p class="mt-1 text-sm text-gray-500">Unidades en las que este usuario puede operar cuando su rol lo permita, independientemente de sus responsabilidades institucionales.</p>
                    <ul class="mt-4 space-y-2 text-sm text-gray-700">
                        @forelse ($accesosOperativos as $acceso)
                            <li class="rounded-lg border border-gray-200 bg-white p-3">{{ $acceso->unidad->nombre }} · {{ $acceso->alcance->etiqueta() }} · {{ $acceso->vigente_desde->format('d/m/Y') }} → {{ $acceso->vigente_hasta?->format('d/m/Y') ?? 'Sin término' }}</li>
                        @empty
                            <li class="text-gray-500">Sin autorizaciones adicionales vigentes.</li>
                        @endforelse
                    </ul>

                    @can('create', App\Models\UserUnidadAcceso::class)
                        <div class="mt-4">
                            <a href="{{ route('admin.accesos.create', ['user_id' => $user->id]) }}" class="text-sm font-medium text-indigo-700 hover:underline">+ Agregar autorización adicional</a>
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
