<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex items-center gap-4 sm:gap-6">
                <a href="{{ route('dashboard') }}"><x-application-logo class="block h-9 w-auto fill-current text-gray-800" /></a>
                <div class="hidden items-center gap-4 sm:flex lg:gap-8">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Inicio</x-nav-link>
                    @canany(['crear-reemplazo', 'reemplazos.revisar'])
                        <x-dropdown align="left" width="w-64">
                            <x-slot name="trigger">
                            <button type="button" :aria-expanded="open" @keydown.escape.stop="open = false" @class([
                                'inline-flex items-center gap-1 border-b-2 px-1 pt-1 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out',
                                'border-indigo-400 text-gray-900 focus:border-indigo-700' => request()->routeIs('reemplazos.*', 'gestion-personas.reemplazos.*'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' => ! (request()->routeIs('reemplazos.*', 'gestion-personas.reemplazos.*')),
                            ])>
                                Trámites<span aria-hidden="true">▾</span>
                            </button>
                            </x-slot>
                            <x-slot name="content">
                                <div @keydown.escape.stop="open = false">
                                    @can('crear-reemplazo')
                                        <x-dropdown-link :href="route('reemplazos.create')" :class="request()->routeIs('reemplazos.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('reemplazos.*') ? 'page' : null">Nueva solicitud de reemplazo</x-dropdown-link>
                                    @endcan
                                    @can('reemplazos.revisar')
                                        <x-dropdown-link :href="route('gestion-personas.reemplazos.index')" :class="request()->routeIs('gestion-personas.reemplazos.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('gestion-personas.reemplazos.*') ? 'page' : null">Revisión de reemplazos</x-dropdown-link>
                                    @endcan
                                </div>
                            </x-slot>
                        </x-dropdown>
                    @endcanany
                    @canany(['personas.ver', 'dotacion.ver'])
                        <x-dropdown align="left" width="w-64">
                            <x-slot name="trigger">
                            <button type="button" :aria-expanded="open" @keydown.escape.stop="open = false" @class([
                                'inline-flex items-center gap-1 border-b-2 px-1 pt-1 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out',
                                'border-indigo-400 text-gray-900 focus:border-indigo-700' => request()->routeIs('admin.personas.*', 'admin.dotacion.*'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' => ! (request()->routeIs('admin.personas.*', 'admin.dotacion.*')),
                            ])>
                                Personas<span aria-hidden="true">▾</span>
                            </button>
                            </x-slot>
                            <x-slot name="content">
                                <div @keydown.escape.stop="open = false">
                                    @can('personas.ver')
                                        <x-dropdown-link :href="route('admin.personas.index')" :class="request()->routeIs('admin.personas.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.personas.*') ? 'page' : null">Personas</x-dropdown-link>
                                    @endcan
                                    @can('dotacion.ver')
                                        <x-dropdown-link :href="route('admin.dotacion.index')" :class="request()->routeIs('admin.dotacion.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.dotacion.*') ? 'page' : null">Dotación</x-dropdown-link>
                                    @endcan
                                </div>
                            </x-slot>
                        </x-dropdown>
                    @endcanany
                    @canany(['admin.usuarios', 'estructura_organizacional.ver', 'responsabilidades.ver', 'accesos_operativos.ver', 'calidades_contractuales.ver'])
                        <x-dropdown align="left" width="w-64">
                            <x-slot name="trigger">
                            <button type="button" :aria-expanded="open" @keydown.escape.stop="open = false" @class([
                                'inline-flex items-center gap-1 border-b-2 px-1 pt-1 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out',
                                'border-indigo-400 text-gray-900 focus:border-indigo-700' => request()->routeIs('admin.usuarios.*', 'admin.estructura.*', 'admin.tipos-organizacionales.*', 'admin.responsabilidades.*', 'admin.accesos.*', 'admin.calidades.*'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' => ! (request()->routeIs('admin.usuarios.*', 'admin.estructura.*', 'admin.tipos-organizacionales.*', 'admin.responsabilidades.*', 'admin.accesos.*', 'admin.calidades.*')),
                            ])>
                                Administración<span aria-hidden="true">▾</span>
                            </button>
                            </x-slot>
                            <x-slot name="content">
                                <div @keydown.escape.stop="open = false">
                                    @can('admin.usuarios')
                                        <x-dropdown-link :href="route('admin.usuarios.index')" :class="request()->routeIs('admin.usuarios.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.usuarios.*') ? 'page' : null">Usuarios y permisos</x-dropdown-link>
                                    @endcan
                                    @can('estructura_organizacional.ver')
                                        <x-dropdown-link :href="route('admin.estructura.index')" :class="request()->routeIs('admin.estructura.*', 'admin.tipos-organizacionales.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.estructura.*', 'admin.tipos-organizacionales.*') ? 'page' : null">Estructura organizacional</x-dropdown-link>
                                    @endcan
                                    @can('responsabilidades.ver')
                                        <x-dropdown-link :href="route('admin.responsabilidades.index')" :class="request()->routeIs('admin.responsabilidades.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.responsabilidades.*') ? 'page' : null">Responsables</x-dropdown-link>
                                    @endcan
                                    @can('accesos_operativos.ver')
                                        <x-dropdown-link :href="route('admin.accesos.index')" :class="request()->routeIs('admin.accesos.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.accesos.*') ? 'page' : null">Accesos operativos</x-dropdown-link>
                                    @endcan
                                    @can('calidades_contractuales.ver')
                                        <x-dropdown-link :href="route('admin.calidades.index')" :class="request()->routeIs('admin.calidades.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''" :aria-current="request()->routeIs('admin.calidades.*') ? 'page' : null">Calidades contractuales</x-dropdown-link>
                                    @endcan
                                </div>
                            </x-slot>
                        </x-dropdown>
                    @endcanany
                </div>
            </div>
            <div class="hidden items-center sm:flex">
                <x-dropdown align="right" width="w-64">
                    <x-slot name="trigger">
                        <button type="button" :aria-expanded="open" @keydown.escape.stop="open = false" @class([
                                'inline-flex items-center gap-1 border-b-2 px-1 pt-1 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out',
                                'border-indigo-400 text-gray-900 focus:border-indigo-700' => request()->routeIs('profile.*'),
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' => ! (request()->routeIs('profile.*')),
                            ])>
                                <span class="max-w-24 truncate lg:max-w-48">{{ Auth::user()->name }}</span><span aria-hidden="true">▾</span>
                            </button>
                    </x-slot>
                    <x-slot name="content">
                        <div @keydown.escape.stop="open = false">
                            <div class="border-b border-gray-100 px-4 py-3 break-words">
                                <div class="text-sm font-medium text-gray-800">{{ Auth::user()->name }}</div>
                                <div class="text-xs text-gray-500">{{ Auth::user()->getRoleNames()->join(', ') ?: 'Sin rol asignado' }}</div>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')" :class="request()->routeIs('profile.*') ? 'bg-indigo-50 text-indigo-700 font-medium' : ''">Mi perfil</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100">Cerrar sesión</button>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
            <button type="button" @click="open = ! open" :aria-expanded="open" aria-controls="navigation-mobile" class="sm:hidden" aria-label="Abrir menú">☰</button>
        </div>
    </div>
    <div id="navigation-mobile" x-show="open" @keydown.escape.stop="open = false" style="display: none;" class="border-t px-4 py-3 sm:hidden">
        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Inicio</x-responsive-nav-link>
        @canany(['crear-reemplazo', 'reemplazos.revisar'])
            <section class="mt-3 border-t border-gray-100 pt-3">
                <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Trámites</h2>
                @can('crear-reemplazo')
                    <x-responsive-nav-link :href="route('reemplazos.create')" :active="request()->routeIs('reemplazos.*')">Nueva solicitud de reemplazo</x-responsive-nav-link>
                @endcan
                @can('reemplazos.revisar')
                    <x-responsive-nav-link :href="route('gestion-personas.reemplazos.index')" :active="request()->routeIs('gestion-personas.reemplazos.*')">Revisión de reemplazos</x-responsive-nav-link>
                @endcan
            </section>
        @endcanany
        @canany(['personas.ver', 'dotacion.ver'])
            <section class="mt-3 border-t border-gray-100 pt-3">
                <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Personas</h2>
                @can('personas.ver')
                    <x-responsive-nav-link :href="route('admin.personas.index')" :active="request()->routeIs('admin.personas.*')">Personas</x-responsive-nav-link>
                @endcan
                @can('dotacion.ver')
                    <x-responsive-nav-link :href="route('admin.dotacion.index')" :active="request()->routeIs('admin.dotacion.*')">Dotación</x-responsive-nav-link>
                @endcan
            </section>
        @endcanany
        @canany(['admin.usuarios', 'estructura_organizacional.ver', 'responsabilidades.ver', 'accesos_operativos.ver', 'calidades_contractuales.ver'])
            <section class="mt-3 border-t border-gray-100 pt-3">
                <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Administración</h2>
                @can('admin.usuarios')
                    <x-responsive-nav-link :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">Usuarios y permisos</x-responsive-nav-link>
                @endcan
                @can('estructura_organizacional.ver')
                    <x-responsive-nav-link :href="route('admin.estructura.index')" :active="request()->routeIs('admin.estructura.*', 'admin.tipos-organizacionales.*')">Estructura organizacional</x-responsive-nav-link>
                @endcan
                @can('responsabilidades.ver')
                    <x-responsive-nav-link :href="route('admin.responsabilidades.index')" :active="request()->routeIs('admin.responsabilidades.*')">Responsables</x-responsive-nav-link>
                @endcan
                @can('accesos_operativos.ver')
                    <x-responsive-nav-link :href="route('admin.accesos.index')" :active="request()->routeIs('admin.accesos.*')">Accesos operativos</x-responsive-nav-link>
                @endcan
                @can('calidades_contractuales.ver')
                    <x-responsive-nav-link :href="route('admin.calidades.index')" :active="request()->routeIs('admin.calidades.*')">Calidades contractuales</x-responsive-nav-link>
                @endcan
            </section>
        @endcanany
        <section class="mt-3 border-t border-gray-100 pt-3">
            <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Usuario</h2>
            <div class="px-3 py-2 break-words">
                <div class="text-sm font-medium text-gray-800">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-500">{{ Auth::user()->getRoleNames()->join(', ') ?: 'Sin rol asignado' }}</div>
            </div>
            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Mi perfil</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left text-base text-gray-600 hover:bg-gray-50 focus:outline-none focus:bg-gray-100">Cerrar sesión</button>
            </form>
        </section>
    </div>
</nav>
