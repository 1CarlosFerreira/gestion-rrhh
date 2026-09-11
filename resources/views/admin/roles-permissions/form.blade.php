<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">{{ $role ? 'Editar rol' : 'Crear rol' }}</h2></x-slot>
    <div class="py-10">
        <form method="POST" action="{{ $role ? route('admin.roles-permisos.update', $role) : route('admin.roles-permisos.store') }}" class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @csrf
            @if ($role)
                @method('PUT')
            @endif

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <x-input-label for="name" value="Nombre del rol" />
                <x-text-input id="name" name="name" class="mt-1 w-full" value="{{ old('name', $role?->name) }}" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </section>

            <section class="space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Permisos</h3>
                    <p class="text-sm text-gray-600">Selecciona los permisos que tendrá este rol.</p>
                </div>
                <x-input-error :messages="$errors->get('permissions')" />

                <div class="grid items-start gap-4 md:grid-cols-2">
                    @foreach ($permisosAgrupados as $grupo)
                        <fieldset class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <legend class="px-1 font-semibold text-gray-900">{{ $grupo['nombre'] }}</legend>
                            <div class="mt-2 space-y-3">
                                @foreach ($grupo['permisos'] as $permission)
                                    <label class="flex items-start gap-3 text-sm text-gray-700">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, old('permissions', $role?->permissions->pluck('name')->all() ?? []), true)) class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>
                                            <span class="block font-medium">{{ str($permission->name)->after('.')->replace(['.', '_'], [' · ', ' '])->ucfirst() }}</span>
                                            <span class="block text-xs text-gray-500">{{ $permission->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar rol</x-primary-button>
                <a class="text-sm text-gray-600 hover:underline" href="{{ route('admin.roles-permisos.index') }}">Cancelar</a>
            </div>
        </form>
    </div>
</x-app-layout>