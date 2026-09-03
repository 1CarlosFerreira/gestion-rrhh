<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi perfil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash-toast />

            <p class="text-gray-600">Consulta la información de tu cuenta personal y sus roles.</p>

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
                        <h3 class="font-semibold text-gray-900">Roles y cuenta</h3>

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

                    <section class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm">La dotación, las responsabilidades y los accesos operativos se incorporarán en una fase posterior.</section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
