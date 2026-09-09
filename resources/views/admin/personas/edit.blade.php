<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Editar persona</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6">
        <form method="POST" action="{{ route('admin.personas.update', $persona) }}" class="grid gap-5 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="rut" value="RUT" />
                <x-text-input type="text" name="rut" id="rut" :value="old('rut', $persona->rut)" maxlength="12" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('rut')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="nombres" value="Nombres" />
                <x-text-input type="text" name="nombres" id="nombres" :value="old('nombres', $persona->nombres)" maxlength="120" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('nombres')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="apellido_paterno" value="Apellido Paterno" />
                <x-text-input type="text" name="apellido_paterno" id="apellido_paterno" :value="old('apellido_paterno', $persona->apellido_paterno)" maxlength="100" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('apellido_paterno')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="apellido_materno" value="Apellido Materno" />
                <x-text-input type="text" name="apellido_materno" id="apellido_materno" :value="old('apellido_materno', $persona->apellido_materno)" maxlength="100" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('apellido_materno')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 md:col-span-2">
            <x-primary-button>Guardar</x-primary-button>
            <a href="{{ route('admin.personas.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
</x-app-layout>
