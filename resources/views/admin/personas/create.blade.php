<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Registrar persona</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
        <form method="POST" action="{{ route('admin.personas.store') }}" class="grid gap-5 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2">
            @csrf

            <div>
                <x-input-label for="rut" value="RUT" />
                <x-rut-input name="rut" id="rut" :value="old('rut')" class="mt-1 block w-full" aria-describedby="rut-ayuda" required />
                <p id="rut-ayuda" class="mt-1 text-xs text-gray-500">Puedes ingresarlo con o sin puntos.</p>
                <x-input-error :messages="$errors->get('rut')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="nombres" value="Nombres" />
                <x-text-input type="text" name="nombres" id="nombres" :value="old('nombres')" maxlength="120" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('nombres')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="apellido_paterno" value="Apellido Paterno" />
                <x-text-input type="text" name="apellido_paterno" id="apellido_paterno" :value="old('apellido_paterno')" maxlength="100" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('apellido_paterno')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="apellido_materno" value="Apellido Materno" />
                <x-text-input type="text" name="apellido_materno" id="apellido_materno" :value="old('apellido_materno')" maxlength="100" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('apellido_materno')" class="mt-2" />
            </div>

            <div class="border-t border-gray-100 pt-4 md:col-span-2">
                <input type="hidden" name="active" value="0">
                <label for="active" class="inline-flex items-center text-sm font-medium text-gray-700">
                    <input type="checkbox" name="active" id="active" value="1" @checked(old('active', true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2">Activo</span>
                </label>
                <x-input-error :messages="$errors->get('active')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 md:col-span-2">
            <x-primary-button>Guardar</x-primary-button>
            <a href="{{ route('admin.personas.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
</x-app-layout>
