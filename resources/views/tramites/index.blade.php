<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-semibold text-slate-800">{{ auth()->user()->can('tramites.ver_todos') ? 'Trámites' : 'Mis trámites' }}</h2><p class="mt-1 text-sm text-slate-600">Consulta, continúa y revisa las solicitudes de tus unidades.</p></div>@can('create', App\Models\Tramite::class)<a class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" href="{{ route('tramites.create') }}">+ Nuevo trámite</a>@endcan</div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">@include('tramites._filters', ['withSearch' => true])@include('tramites._table')</div></div>
</x-app-layout>
