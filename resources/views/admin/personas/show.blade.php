@extends('layouts.admin')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Ficha de Persona</h1>

    <div class="mb-6">
        <h2 class="text-xl font-semibold mb-2">Datos Personales</h2>
        <p><strong>RUT:</strong> {{ $persona->rut }}</p>
        <p><strong>Nombre Completo:</strong> {{ $persona->nombre_completo }}</p>
        <p><strong>Activo:</strong> {{ $persona->active ? 'Sí' : 'No' }}</p>
        @if ($persona->user)
            <p><strong>Usuario asociado:</strong> {{ $persona->user->email }}</p>
        @endif
    </div>

    <div>
        <h2 class="text-xl font-semibold mb-2">Dotación</h2>
        <a href="{{ route('admin.dotacion.create') }}" class="bg-green-600 text-white px-4 py-1 rounded mb-4 inline-block">Agregar vínculo de dotación</a>

        @if ($persona->vinculosDotacion->isEmpty())
            <p>No hay vínculos de dotación registrados.</p>
        @else
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="border px-4 py-2">Unidad</th>
                        <th class="border px-4 py-2">Estamento</th>
                        <th class="border px-4 py-2">Profesión</th>
                        <th class="border px-4 py-2">Calidad Contractual</th>
                        <th class="border px-4 py-2">Cargo/Función</th>
                        <th class="border px-4 py-2">Grado EUS</th>
                        <th class="border px-4 py-2">Desde</th>
                        <th class="border px-4 py-2">Hasta</th>
                        <th class="border px-4 py-2">Estado</th>
                        <th class="border px-4 py-2">Origen</th>
                        <th class="border px-4 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($persona->vinculosDotacion as $vinculo)
                    <tr>
                        <td class="border px-4 py-2">{{ $vinculo->unidad->nombre ?? '' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->estamento->nombre ?? '' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->profesion->nombre ?? '' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->calidadContractual->nombre ?? '' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->cargo_funcion }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->grado_eus ?? '' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->vigente_desde->format('d/m/Y') }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->vigente_hasta?->format('d/m/Y') ?? '-' }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->estadoEn(now())->name }}</td>
                        <td class="border px-4 py-2">{{ $vinculo->origen->name ?? '' }}</td>
                        <td class="border px-4 py-2">
                            <a href="{{ route('admin.dotacion.edit', $vinculo) }}" class="text-green-600 hover:underline">Editar</a>
                            @if ($vinculo->estadoEn(now()) !== \App\Enums\EstadoVinculoDotacion::FINALIZADO)
                                <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="inline-block ml-2" onsubmit="return confirm('¿Cerrar vínculo? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-red-600 hover:underline">Cerrar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection