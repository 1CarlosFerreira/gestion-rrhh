<div class="grid gap-3 px-4 py-3 sm:px-5 lg:grid-cols-[minmax(12rem,1.3fr)_minmax(13rem,1fr)_minmax(10rem,0.8fr)_auto] lg:items-center">
    <div class="min-w-0">
        <p class="truncate text-sm font-medium {{ $historico ? 'text-gray-700' : 'text-gray-900' }}">{{ $acceso->unidad->nombre }}</p>
        <p class="mt-0.5 text-xs text-gray-500">Creado por {{ $acceso->creadoPor->name }}</p>
    </div>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $acceso->alcance->value === 'UNIDAD_Y_DESCENDIENTES' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-50 text-blue-700' }}">{{ $acceso->alcance->value === 'UNIDAD_Y_DESCENDIENTES' ? 'Unidad + descendientes' : 'Solo esta unidad' }}</span>
            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $historico ? 'bg-gray-200 text-gray-700' : ($acceso->user->active ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800') }}">{{ $historico ? 'Histórico' : ($acceso->user->active ? 'Vigente' : 'Cuenta inactiva') }}</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ $acceso->vigente_desde->format('d/m/Y') }} — {{ $acceso->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</p>
    </div>
    <div class="min-w-0" x-data="{ detalleAbierto: false }">
        <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 hover:underline" x-on:click="detalleAbierto = ! detalleAbierto" x-bind:aria-expanded="detalleAbierto.toString()">
            {{ $unidadesAlcanzadas->count() }} {{ $unidadesAlcanzadas->count() === 1 ? 'unidad alcanzada' : 'unidades alcanzadas' }}
            <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': detalleAbierto }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
        </button>
        <ul x-cloak x-show="detalleAbierto" class="mt-1.5 space-y-1 text-xs text-gray-500">
            @forelse ($unidadesAlcanzadas as $unidadAlcanzada)
                <li>{{ $unidadAlcanzada->nombre }}</li>
            @empty
                <li>Ninguna</li>
            @endforelse
        </ul>
    </div>
    @can('accesos_operativos.gestionar')
        <div class="flex flex-wrap items-end gap-3 lg:justify-end">
            <a href="{{ route('admin.accesos.edit', $acceso) }}" class="pb-2 text-sm font-medium text-indigo-700 hover:underline">Editar</a>
            @if (! $acceso->vigente_hasta)
                <form method="POST" action="{{ route('admin.accesos.close', $acceso) }}" class="flex items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar este acceso?')">
                    @csrf
                    @method('PATCH')
                    <label class="text-xs font-medium text-gray-500">Término
                        <input type="date" name="vigente_hasta" min="{{ $acceso->vigente_desde->toDateString() }}" required class="mt-0.5 block w-32 rounded-md border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                    <button type="submit" class="mb-0.5 text-sm font-medium text-red-700 hover:underline">Cerrar</button>
                </form>
            @endif
        </div>
    @endcan
</div>
