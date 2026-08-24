<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Personas</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif
        <div class="flex flex-wrap justify-between gap-3">
            <form class="flex gap-2" method="GET"><x-text-input name="q" :value="request('q')" placeholder="RUT, nombre o apellido"/><x-secondary-button>Buscar</x-secondary-button></form>
            @can('personas.gestionar')<a class="rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white" href="{{ route('personas.create') }}">Nueva persona</a>@endcan
        </div>
        <div class="overflow-hidden rounded-lg bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-gray-50"><tr><th class="p-3">Persona</th><th class="p-3">RUT</th><th class="p-3">Estado</th><th class="p-3"></th></tr></thead><tbody>
            @forelse($personas as $persona)<tr class="border-t"><td class="p-3">{{ $persona->nombre_completo }}</td><td class="p-3">{{ \App\Support\Rut\Rut::format($persona->rut) }}</td><td class="p-3">{{ $persona->active ? 'Activa' : 'Inactiva' }}</td><td class="p-3 text-right"><a class="text-blue-700" href="{{ route('personas.show', $persona) }}">Ver</a></td></tr>@empty<tr><td colspan="4" class="p-5 text-center text-gray-500">Sin resultados.</td></tr>@endforelse
        </tbody></table></div>{{ $personas->links() }}
    </div></div>
</x-app-layout>
