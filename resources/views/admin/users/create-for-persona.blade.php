<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Crear acceso al sistema</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6">
            @if ($continuarPerfil)
                <x-admin-process-steps current="acceso" />
            @endif

            <form method="POST" action="{{ route('admin.usuarios.store-for-persona', ['persona' => $persona, 'continuar_perfil' => $continuarPerfil ? 1 : null]) }}" class="space-y-5">
                @csrf

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="font-semibold text-gray-900">{{ $persona->nombre_completo }}</p>
                    <p class="mt-1 text-sm text-gray-600">RUT {{ $persona->rut }}</p>
                    <p class="mt-2 text-sm text-green-700">Estado inicial: Activo</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-900">Credenciales</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <x-input-label for="email" value="Correo institucional" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus autocomplete="email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="password" value="Contraseña inicial" />
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            </div>
                        </div>
                    </div>

                    <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-500">La cuenta se creará inicialmente sin roles ni accesos operativos.</p>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('admin.personas.show', $persona) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">Cancelar</a>
                    <x-primary-button class="justify-center">{{ $continuarPerfil ? 'Crear usuario y continuar →' : 'Crear usuario' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
