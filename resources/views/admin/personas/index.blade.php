@extends('layouts.admin')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Personas</h1>

    <form method="GET" action="{{ route('admin.personas.index') }}" class="mb-4 flex space-x-2">
        <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o RUT" class="border rounded px-2 py-1 flex-grow" />
        <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded">Buscar</button>
        <a href="{{ route('admin.personas.create') }}" class="bg-green-600 text-white px-4 py-1 rounded">Crear Persona</a>
    </form>

    <table class="min-w-full bg-white border border-gray-200">
        <thead>
            <tr>
                <th class="border px-4 py-2">RUT</th>
                <th class="border px-4 py-2">Nombre Completo</th>
                <th class="border px-4 py-2">Activo</th>
                <th class="border px-4 py-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($personas as $persona)
            <tr>
                <td class="border px-4 py-2">{{ $persona->rut }}</td>
                <td class="border px-4 py-2">{{ $persona->nombre_completo }}</td>
                <td class="border px-4 py-2">{{ $persona->active ? 'Sí' : 'No' }}</td>
                <td class="border px-4 py-2">
                    <a href="{{ route('admin.personas.show', $persona) }}" class="text-blue-600 hover:underline">Ver</a>
                    <a href="{{ route('admin.personas.edit', $persona) }}" class="ml-2 text-green-600 hover:underline">Editar</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $personas->withQueryString()->links() }}
    </div>
</div>
@endsection