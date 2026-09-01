@php($usesGestionPersonasNavigation = auth()->user()->can('reemplazos.revisar_personal') && ! auth()->user()->can('reemplazos.crear'))
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Inicio
                    </x-nav-link>
                    @if($usesGestionPersonasNavigation)
                        <x-nav-link :href="route('gestion-personas.bandeja')" :active="request()->routeIs('gestion-personas.*')">Bandeja de solicitudes</x-nav-link>
                        @if(auth()->user()->canAny(['personas.ver', 'dotacion.ver']))<x-nav-link :href="route('personas.index')" :active="request()->routeIs('personas.*', 'dotacion.*')">Personas y dotación</x-nav-link>@endif
                    @else
                        @canany(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos'])
                            <x-nav-link :href="route('tramites.index')" :active="request()->routeIs('tramites.*')">{{ auth()->user()->can('tramites.ver_todos') ? 'Trámites' : 'Mis trámites' }}</x-nav-link>
                        @endcanany
                        @if(auth()->user()->can('tramites.ver_todos')) @can('personas.ver')<x-nav-link :href="route('personas.index')" :active="request()->routeIs('personas.*')">Personas</x-nav-link>@endcan @endif
                        @can('tramites.ver_todos')<x-nav-link :href="route('gestion-personas.bandeja')" :active="request()->routeIs('gestion-personas.*')">Bandeja GP</x-nav-link>@endcan
                        @can('dotacion.ver')<x-nav-link :href="route('dotacion.index')" :active="request()->routeIs('dotacion.*')">Dotación</x-nav-link>@endcan
                    @endif
                    @can('admin.usuarios')
                        <x-nav-link :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">Usuarios</x-nav-link>
                    @endcan
                    @can('admin.catalogos')
                        <x-nav-link :href="route('admin.catalogos.index')" :active="request()->routeIs('admin.catalogos.*')">Catálogos</x-nav-link>
                        <x-nav-link :href="route('admin.plantillas.index')" :active="request()->routeIs('admin.plantillas.*')">Plantillas</x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Authenticated user -->
            <div class="hidden items-center sm:flex sm:ms-6" x-data="{ accountOpen: false }" @keydown.escape.window="accountOpen = false">
                <div class="relative">
                    <button type="button" @click="accountOpen = ! accountOpen" @click.outside="accountOpen = false" :aria-expanded="accountOpen.toString()" aria-haspopup="menu" aria-label="Abrir menú de usuario" class="flex max-w-xs items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-800">{{ collect(explode(' ', Auth::user()->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('') }}</span>
                        <span class="min-w-0"><span class="block truncate text-sm font-medium text-gray-800">{{ Auth::user()->name }}</span><span class="block truncate text-xs text-gray-500">{{ Auth::user()->getRoleNames()->first() ?? 'Sin rol asignado' }}</span></span><span aria-hidden="true" class="text-gray-400">⌄</span>
                    </button>
                    <div x-show="accountOpen" x-transition x-cloak role="menu" class="absolute right-0 z-50 mt-2 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <div class="border-b border-gray-100 px-4 py-3 text-sm text-gray-600"><p class="truncate font-medium text-gray-800">{{ Auth::user()->name }}</p><p class="truncate">{{ Auth::user()->email }}</p></div>
                        <a role="menuitem" href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('profile.*') ? 'bg-indigo-50 text-indigo-800' : 'text-gray-700 hover:bg-gray-50' }}">Mi perfil</a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">@csrf<button type="submit" role="menuitem" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Cerrar sesión</button></form>
                    </div>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Inicio
            </x-responsive-nav-link>
            @if($usesGestionPersonasNavigation)
                <x-responsive-nav-link :href="route('gestion-personas.bandeja')">Bandeja de solicitudes</x-responsive-nav-link>
                @if(auth()->user()->canAny(['personas.ver', 'dotacion.ver']))<x-responsive-nav-link :href="route('personas.index')">Personas y dotación</x-responsive-nav-link>@endif
            @else
                @canany(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos'])<x-responsive-nav-link :href="route('tramites.index')">{{ auth()->user()->can('tramites.ver_todos') ? 'Trámites' : 'Mis trámites' }}</x-responsive-nav-link>@endcanany
                @if(auth()->user()->can('tramites.ver_todos')) @can('personas.ver')<x-responsive-nav-link :href="route('personas.index')">Personas</x-responsive-nav-link>@endcan @endif
                @can('tramites.ver_todos')<x-responsive-nav-link :href="route('gestion-personas.bandeja')">Bandeja GP</x-responsive-nav-link>@endcan
                @can('dotacion.ver')<x-responsive-nav-link :href="route('dotacion.index')">Dotación</x-responsive-nav-link>@endcan
            @endif
            @can('admin.usuarios')
                <x-responsive-nav-link :href="route('admin.usuarios.index')">Usuarios</x-responsive-nav-link>
            @endcan
            @can('admin.catalogos')
                <x-responsive-nav-link :href="route('admin.catalogos.index')">Catálogos</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.plantillas.index')">Plantillas</x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ Auth::user()->getRoleNames()->first() ?? 'Sin rol asignado' }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Mi perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-start text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-800 focus:bg-gray-50 focus:text-gray-800 focus:outline-none">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
