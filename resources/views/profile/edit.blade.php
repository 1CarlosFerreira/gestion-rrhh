<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi perfil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-gray-600">Información de tu cuenta y accesos dentro del sistema.</p>
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow-sm md:grid-cols-2"><div><h3 class="font-semibold">Resumen de cuenta</h3><p>Nombre: {{ $user->name }}</p><p>Correo electrónico: {{ $user->email }}</p><p>Rol(es): {{ $user->getRoleNames()->join(', ') ?: 'Sin rol' }}</p><p>Estado: {{ $user->active ? 'Activo' : 'Inactivo' }}</p><p>Último acceso: {{ $user->last_login_at?->format('d-m-Y H:i') ?? 'Sin registro' }}</p></div><div><h3 class="font-semibold">Unidades/Servicios habilitados</h3><div class="mt-2 space-y-2">@forelse($user->asignacionesUnidad as $asignacion)<p class="rounded border p-2 text-sm">{{ $asignacion->unidad->nombre }} · {{ $asignacion->valid_from?->format('d-m-Y') ?? 'Sin inicio' }} a {{ $asignacion->valid_to?->format('d-m-Y') ?? 'Sin término' }} · {{ $asignacion->active ? 'Activa' : 'Inactiva' }}</p>@empty<p class="text-sm text-gray-500">Sin unidades asignadas.</p>@endforelse</div></div></div>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
