<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Ficha de Persona</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <p class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-800">Datos de Persona</h2>
            <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <p><strong class="font-medium text-gray-600">RUT:</strong> {{ $persona->rut }}</p>
            <p><strong class="font-medium text-gray-600">Nombres:</strong> {{ $persona->nombres }}</p>
            <p><strong class="font-medium text-gray-600">Apellido paterno:</strong> {{ $persona->apellido_paterno ?? '-' }}</p>
            <p><strong class="font-medium text-gray-600">Apellido materno:</strong> {{ $persona->apellido_materno ?? '-' }}</p>
            <p><strong class="font-medium text-gray-600">Estado:</strong> <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $persona->active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $persona->active ? 'Activo' : 'Inactivo' }}</span></p>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
            @can('personas.gestionar')
                <a href="{{ route('admin.personas.edit', $persona) }}" class="text-indigo-700 hover:underline">Editar Persona</a>
            @endcan
            <a href="{{ route('admin.personas.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver al listado</a>
            </div>
        </section>

        @if ($verDotacion)
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Dotación</h2>
                @if ($puedeAgregarVinculo)
                <a href="{{ route('admin.dotacion.create', ['persona_id' => $persona->id]) }}" class="mb-4 inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Agregar vínculo de dotación</a>
                @endif

                @if ($vinculos->isEmpty())
                    <p>No hay vínculos de dotación visibles en su alcance.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                            <tr class="border-b">
                                <th class="p-3 align-top">Unidad</th>
                                <th class="p-3 align-top">Estamento</th>
                                <th class="p-3 align-top">Profesión</th>
                                <th class="p-3 align-top">Calidad Contractual</th>
                                <th class="p-3 align-top">Cargo/Función</th>
                                <th class="p-3 align-top">Grado EUS</th>
                                <th class="p-3 align-top">Desde</th>
                                <th class="p-3 align-top">Hasta</th>
                                <th class="p-3 align-top">Estado</th>
                                <th class="p-3 align-top">Origen</th>
                                <th class="p-3 align-top">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vinculos as $vinculo)
                            <tr class="border-b last:border-b-0 hover:bg-gray-50">
                                <td class="p-3 align-top">{{ $vinculo->unidad->nombre ?? '' }}</td>
                                <td class="p-3 align-top">{{ $vinculo->estamento->nombre ?? '' }}</td>
                                <td class="p-3 align-top">{{ $vinculo->profesion->nombre ?? '' }}</td>
                                <td class="p-3 align-top">{{ $vinculo->calidadContractual->nombre ?? '' }}</td>
                                <td class="p-3 align-top">{{ $vinculo->cargo_funcion }}</td>
                                <td class="p-3 align-top">{{ $vinculo->grado_eus ?? '' }}</td>
                                <td class="p-3 align-top">{{ $vinculo->vigente_desde->format('d/m/Y') }}</td>
                                <td class="p-3 align-top">{{ $vinculo->vigente_hasta?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3 align-top"><span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $vinculo->estadoEn(now())->name }}</span></td>
                                <td class="p-3 align-top">{{ $vinculo->origen->name ?? '' }}</td>
                                <td class="p-3 align-top">
                                    @can('dotacion.gestionar')
                                    @can('update', $vinculo)
                                    <a href="{{ route('admin.dotacion.edit', $vinculo) }}" class="text-indigo-700 hover:underline">Editar</a>
                                    @if ($vinculo->estadoEn(now()) !== \App\Enums\EstadoVinculoDotacion::FINALIZADO)
                                        <form method="POST" action="{{ route('admin.dotacion.close', $vinculo) }}" class="mt-3 flex min-w-max flex-col items-start gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-xs text-gray-600" for="vigente_hasta_{{ $vinculo->id }}">Fecha de término</label>
                                            <input type="date" name="vigente_hasta" id="vigente_hasta_{{ $vinculo->id }}" min="{{ $vinculo->vigente_desde->toDateString() }}" value="{{ old('vigente_hasta', $vinculo->vigente_hasta?->toDateString()) }}" class="w-40 rounded border-gray-300 text-sm" required>
                                            <button type="submit" class="text-indigo-700 hover:underline">Cerrar</button>
                                        </form>
                                    @endif
                                    @endcan
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </section>
        @endif
        </div>
    </div>
</x-app-layout>
