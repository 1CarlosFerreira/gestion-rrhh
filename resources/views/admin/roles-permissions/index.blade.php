<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Roles y permisos</h2></x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
            @endif

            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-gray-600">Administra los roles y sus permisos usando la configuración de Spatie.</p>
                <a class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" href="{{ route('admin.roles-permisos.create') }}">Crear rol</a>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                @forelse ($roles as $role)
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 p-5 last:border-b-0">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $role->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $role->permissions_count }} {{ $role->permissions_count === 1 ? 'permiso' : 'permisos' }} · {{ $role->users_count }} {{ $role->users_count === 1 ? 'usuario' : 'usuarios' }}</p>
                        </div>
                        <a class="text-sm font-medium text-indigo-700 hover:underline" href="{{ route('admin.roles-permisos.edit', $role) }}">Editar rol</a>
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-600">No existen roles configurados.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>