<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Crear acceso al sistema</h2></x-slot>

    <div class="py-10">
        <form method="POST" action="{{ route('admin.usuarios.store-for-persona', $persona) }}" class="mx-auto max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">
            @csrf

            <div>
                <x-input-label value="Persona" />
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $persona->nombre_completo }}</p>
            </div>
            <div>
                <x-input-label value="RUT" />
                <p class="mt-1 text-sm text-gray-700">{{ $persona->rut }}</p>
            </div>
            <div>
                <x-input-label value="Estado inicial" />
                <p class="mt-1 text-sm text-gray-700">Activo</p>
            </div>
            <div>
                <x-input-label for="email" value="Correo institucional" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus autocomplete="email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password" value="Contraseña inicial" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Confirmar contraseña inicial" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            </div>

            <p class="text-sm text-gray-600">La cuenta se creará sin roles ni accesos operativos.</p>

            <div class="flex flex-wrap gap-3">
                <x-primary-button>Crear usuario</x-primary-button>
                <a href="{{ route('admin.personas.show', $persona) }}" class="inline-flex items-center text-sm text-gray-700 hover:underline">Cancelar</a>
            </div>
        </form>
    </div>
</x-app-layout>
