<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Personas</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" action="{{ route('admin.personas.index') }}" class="flex flex-col gap-3 sm:flex-1 sm:flex-row sm:items-center">
                <x-text-input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="RUT, nombres o apellido" class="w-full sm:min-w-0 sm:flex-1" />
                <x-secondary-button type="submit" class="justify-center">Buscar</x-secondary-button>
            </form>
            <a href="{{ route('admin.personas.create') }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Registrar persona</a>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                <tr class="border-b">
                    <th class="px-4 py-3">RUT</th>
                    <th class="px-4 py-3">Nombre completo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($personas as $persona)
                <tr class="border-b last:border-b-0 hover:bg-gray-50">
                    <td class="whitespace-nowrap px-4 py-3">{{ $persona->rut }}</td>
                    <td class="px-4 py-3">{{ $persona->nombre_completo }}</td>
                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $persona->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $persona->active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('admin.personas.show', $persona) }}" class="text-indigo-700 hover:underline">Ver</a>
                        <a href="{{ route('admin.personas.edit', $persona) }}" class="ml-3 text-indigo-700 hover:underline">Editar</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div>
            {{ $personas->withQueryString()->links() }}
        </div>
        </div>
    </div>
</x-app-layout>
