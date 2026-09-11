<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Usuarios</h2></x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_12rem_14rem_auto]">
                <x-text-input name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, RUT o correo" aria-label="Buscar usuarios" />
                <select name="estado" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los estados</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                </select>
                <select name="rol" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(request('rol') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (request()->hasAny(['buscar', 'estado', 'rol']))
                        <a href="{{ route('admin.usuarios.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:underline">Limpiar</a>
                    @endif
                </div>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">RUT / correo</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Roles</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr id="usuario-{{ $user->id }}" class="{{ request()->integer('user_id') === $user->id ? 'bg-indigo-50' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <span class="block whitespace-nowrap">{{ $user->rut ?? 'Sin RUT' }}</span>
                                        <span class="block">{{ $user->email }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $user->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($user->roles as $role)
                                                <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-gray-500">Sin roles</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <button type="button" class="text-sm font-medium text-indigo-700 hover:underline" x-data x-on:click.prevent="$dispatch('open-modal', 'gestionar-usuario-{{ $user->id }}')">Gestionar</button>

                                        <x-modal name="gestionar-usuario-{{ $user->id }}" maxWidth="2xl" focusable>
                                    <div class="p-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h2 class="text-lg font-semibold text-gray-900">Gestionar usuario</h2>
                                                <p class="mt-2 font-medium text-gray-800">{{ $user->name }}</p>
                                                <p class="text-sm text-gray-600">{{ $user->rut ?? 'Sin RUT' }} · {{ $user->email }}</p>
                                                <p class="mt-1 text-sm text-gray-600">Estado: {{ $user->active ? 'Activo' : 'Inactivo' }}</p>
                                            </div>
                                            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" x-on:click="$dispatch('close')">Cerrar</button>
                                        </div>

                                        <form method="POST" action="{{ route('admin.usuarios.roles.update', $user) }}" class="mt-6">
                                            @csrf
                                            @method('PUT')
                                            <fieldset>
                                                <legend class="text-sm font-semibold text-gray-800">Roles</legend>
                                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                    @foreach ($roles as $role)
                                                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                                                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($user->hasRole($role)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                            <span>{{ $role->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                            <div class="mt-5">
                                                <x-primary-button>Guardar roles</x-primary-button>
                                            </div>
                                        </form>

                                        <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-5">
                                            @can('accesos_operativos.ver')
                                                <a class="text-sm text-indigo-700 hover:underline" href="{{ route('admin.accesos.index', ['usuario' => $user->email]) }}">Accesos operativos</a>
                                            @endcan

                                            @if (! $user->is(auth()->user()))
                                                <form method="POST" action="{{ route('admin.usuarios.activo', $user) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-secondary-button type="submit">{{ $user->active ? 'Desactivar usuario' : 'Activar usuario' }}</x-secondary-button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-500">No puedes desactivar tu propia cuenta.</span>
                                            @endif
                                        </div>
                                    </div>
                                        </x-modal>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No se encontraron usuarios.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
