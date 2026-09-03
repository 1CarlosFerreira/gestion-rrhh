<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Inicio</h2></x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-indigo-100 bg-white p-8 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Bienvenido/a, {{ auth()->user()->name }}</h1>
                <p class="mt-3 text-slate-600">La versión 2 del Sistema de Gestión de Solicitudes de RRHH se encuentra en construcción.</p>
            </section>
        </div>
    </div>
</x-app-layout>
