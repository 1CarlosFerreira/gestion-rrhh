<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}"><x-application-logo class="block h-9 w-auto fill-current text-gray-800" /></a>
                <div class="hidden gap-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Inicio</x-nav-link>
                    @can('admin.usuarios')
                        <x-nav-link :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">Usuarios, roles y permisos</x-nav-link>
                    @endcan
                    @can('estructura_organizacional.ver')<x-nav-link :href="route('admin.estructura.index')" :active="request()->routeIs('admin.estructura.*', 'admin.tipos-organizacionales.*')">Estructura organizacional</x-nav-link>@endcan
                </div>
            </div>
            <div class="hidden items-center gap-4 sm:flex">
                <div class="text-right"><div class="text-sm font-medium text-gray-800">{{ Auth::user()->name }}</div><div class="text-xs text-gray-500">{{ Auth::user()->getRoleNames()->join(', ') ?: 'Sin rol asignado' }}</div></div>
                <a href="{{ route('profile.edit') }}" class="text-sm text-gray-600 hover:text-gray-900">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<x-secondary-button type="submit">Cerrar sesión</x-secondary-button></form>
            </div>
            <button type="button" @click="open = ! open" class="sm:hidden" aria-label="Abrir menú">☰</button>
        </div>
    </div>
    <div x-show="open" class="border-t px-4 py-3 sm:hidden">
        <x-responsive-nav-link :href="route('dashboard')">Inicio</x-responsive-nav-link>
        @can('admin.usuarios')<x-responsive-nav-link :href="route('admin.usuarios.index')">Usuarios, roles y permisos</x-responsive-nav-link>@endcan
        @can('estructura_organizacional.ver')<x-responsive-nav-link :href="route('admin.estructura.index')">Estructura organizacional</x-responsive-nav-link>@endcan
        <x-responsive-nav-link :href="route('profile.edit')">Mi perfil</x-responsive-nav-link>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-base text-gray-600">Cerrar sesión</button></form>
    </div>
</nav>
