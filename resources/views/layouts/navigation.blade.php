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
                    @can('personas.ver')
                        <x-nav-link :href="route('personas.index')" :active="request()->routeIs('personas.*')">Personas</x-nav-link>
                    @endcan
                    @canany(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos'])
                        <x-nav-link :href="route('tramites.index')" :active="request()->routeIs('tramites.*')">Trámites</x-nav-link>
                    @endcanany
                    @can('reemplazos.crear')
                        <x-nav-link :href="route('reemplazos.create')" :active="request()->routeIs('reemplazos.*')">Nuevo reemplazo</x-nav-link>
                    @endcan
                    @can('horas_extra.crear')
                        <x-nav-link :href="route('horas-extra.create')" :active="request()->routeIs('horas-extra.*')">Nuevas horas extra</x-nav-link>
                    @endcan
                    @can('tramites.ver_todos')
                        <x-nav-link :href="route('gestion-personas.bandeja')" :active="request()->routeIs('gestion-personas.*')">Bandeja GP</x-nav-link>
                    @endcan
                    @can('dotacion.ver')
                        <x-nav-link :href="route('dotacion.index')" :active="request()->routeIs('dotacion.*')">Dotación</x-nav-link>
                    @endcan
                    @can('admin.usuarios')
                        <x-nav-link :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">Usuarios</x-nav-link>
                    @endcan
                    @can('admin.catalogos')
                        <x-nav-link :href="route('admin.catalogos.index')" :active="request()->routeIs('admin.catalogos.*')">Catálogos</x-nav-link>
                        <x-nav-link :href="route('admin.plantillas.index')" :active="request()->routeIs('admin.plantillas.*')">Plantillas</x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Perfil
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
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
            @can('personas.ver')
                <x-responsive-nav-link :href="route('personas.index')">Personas</x-responsive-nav-link>
            @endcan
            @canany(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos'])
                <x-responsive-nav-link :href="route('tramites.index')">Trámites</x-responsive-nav-link>
            @endcanany
            @can('reemplazos.crear')
                <x-responsive-nav-link :href="route('reemplazos.create')">Nuevo reemplazo</x-responsive-nav-link>
            @endcan
            @can('horas_extra.crear')
                <x-responsive-nav-link :href="route('horas-extra.create')">Nuevas horas extra</x-responsive-nav-link>
            @endcan
            @can('tramites.ver_todos')
                <x-responsive-nav-link :href="route('gestion-personas.bandeja')">Bandeja GP</x-responsive-nav-link>
            @endcan
            @can('dotacion.ver')
                <x-responsive-nav-link :href="route('dotacion.index')">Dotación</x-responsive-nav-link>
            @endcan
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
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Perfil
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
