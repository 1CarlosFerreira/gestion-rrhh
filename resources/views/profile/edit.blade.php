<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi perfil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash-toast />

            <p class="text-gray-600">Consulta la información de tu cuenta y tus asignaciones en el sistema.</p>

            <div class="grid items-start gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        @include('profile.partials.update-profile-information-form')
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Rol y asignación</h3>

                        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">Roles vigentes</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($user->getRoleNames() as $rol)
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-sm font-medium text-indigo-800">{{ $rol }}</span>
                            @empty
                                <span class="text-sm text-gray-500">Sin rol asignado.</span>
                            @endforelse
                        </div>

                        <p class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-500">Estado de cuenta</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-sm font-medium {{ $user->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $user->active ? 'Activa' : 'Inactiva' }}</span>

                        <p class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-500">Último acceso</p>
                        <p class="mt-2 text-sm text-gray-700">{{ $user->last_login_at?->format('d-m-Y H:i') ?? 'Aún no hay un acceso registrado.' }}</p>
                    </section>

                    <section class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Unidades asignadas</h3>

                        <div class="mt-4 space-y-3">
                            @forelse($user->asignacionesUnidad as $asignacion)
                                <article class="rounded-lg border border-gray-200 p-3 text-sm">
                                    <p class="font-medium text-gray-800">{{ $asignacion->unidad->nombre }}</p>
                                    <p class="mt-2 text-xs text-gray-500">Vigencia: {{ $asignacion->valid_from?->format('d-m-Y') ?? 'Sin fecha de inicio' }}{{ $asignacion->valid_to ? ' a '.$asignacion->valid_to->format('d-m-Y') : '' }}</p>
                                    <span class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $asignacion->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $asignacion->active ? 'Vigente' : 'Inactiva' }}</span>
                                </article>
                            @empty
                                <p class="text-sm text-gray-500">Sin unidades asignadas.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
