<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Estructura organizacional</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form class="flex flex-wrap gap-2"><x-text-input name="buscar" value="{{ $buscar }}" placeholder="Nombre, código o sigla"/><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="incluir_inactivos" value="1" @checked($incluirInactivos)>Mostrar inactivos</label><x-secondary-button>Buscar</x-secondary-button></form>
            @can('estructura_organizacional.gestionar')<div class="flex gap-2"><a class="rounded bg-indigo-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('admin.estructura.create') }}">Crear nodo raíz</a><a class="rounded border px-4 py-2 text-sm" href="{{ route('admin.tipos-organizacionales.index') }}">Tipos organizacionales</a></div>@endcan
        </div>
        <section class="rounded-xl bg-white p-5 shadow-sm">
            @if($buscar !== '')
                <h3 class="mb-3 font-semibold">Resultados</h3>
                @forelse($resultados as $unidad) @include('admin.estructura.partials.node', ['unidad' => $unidad, 'nivel' => 0, 'recursivo' => false]) @empty <p class="text-sm text-gray-500">Sin resultados.</p> @endforelse
            @else
                @forelse($raices as $unidad) @include('admin.estructura.partials.node', ['unidad' => $unidad, 'nivel' => 0, 'recursivo' => true]) @empty <p class="text-sm text-gray-500">No hay una estructura institucional cargada.</p> @endforelse
            @endif
        </section>
    </div></div>
</x-app-layout>
