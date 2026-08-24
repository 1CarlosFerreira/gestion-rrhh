<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Usuarios y roles</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif
        @foreach ($users as $user)
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div><h3 class="font-semibold">{{ $user->name }}</h3><p class="text-sm text-gray-600">{{ $user->email }} · {{ $user->active ? 'Activo' : 'Inactivo' }}</p></div>
                    <form method="POST" action="{{ route('admin.usuarios.activo', $user) }}">@csrf @method('PATCH')
                        <x-secondary-button type="submit">{{ $user->active ? 'Desactivar' : 'Activar' }}</x-secondary-button>
                    </form>
                </div>
                <form method="POST" action="{{ route('admin.usuarios.roles.update', $user) }}" class="mt-4 flex flex-wrap items-center gap-4">@csrf @method('PUT')
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($user->hasRole($role))><span>{{ $role->name }}</span></label>
                    @endforeach
                    <x-primary-button>Guardar roles</x-primary-button>
                </form>
                @can('usuarios.unidades.gestionar')
                    <div class="mt-5 border-t pt-4"><h4 class="font-medium">Unidades habilitadas</h4>
                        <div class="mt-2 flex flex-wrap gap-2">@forelse($user->asignacionesUnidad as $asignacion)<form method="POST" action="{{ route('admin.usuarios.unidades.toggle', [$user, $asignacion]) }}">@csrf @method('PATCH')<button class="rounded border px-3 py-1 text-sm {{ $asignacion->active ? 'bg-green-50' : 'bg-gray-100 text-gray-500' }}">{{ $asignacion->unidad->nombre }} · {{ $asignacion->active ? 'activa' : 'inactiva' }}</button></form>@empty<span class="text-sm text-gray-500">Sin asignaciones.</span>@endforelse</div>
                        <form method="POST" action="{{ route('admin.usuarios.unidades.store', $user) }}" class="mt-3 flex flex-wrap gap-2">@csrf<select name="unidad_servicio_id" required class="rounded border-gray-300 text-sm"><option value="">Seleccionar unidad</option>@foreach($unidades as $unidad)<option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>@endforeach</select><x-text-input type="date" name="valid_from"/><x-text-input type="date" name="valid_to"/><x-secondary-button>Asignar/reactivar</x-secondary-button></form>
                    </div>
                @endcan
            </section>
        @endforeach
    </div></div>
</x-app-layout>
