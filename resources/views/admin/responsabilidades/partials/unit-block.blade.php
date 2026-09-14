@php($unidad = $responsabilidadesUnidad->first()->unidad)

<article @class([
    'overflow-hidden rounded-xl border shadow-sm',
    'border-gray-200 bg-white' => ! $historica,
    'border-gray-200 bg-gray-50/80' => $historica,
])>
    <button type="button" @class([
        'flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition sm:px-5',
        'border-gray-100 bg-gray-50/70' => ! $historica,
        'border-gray-200 bg-gray-100/80' => $historica,
    ]) x-on:click="cambiar('{{ $bloqueId }}')" x-bind:aria-expanded="Boolean(abiertas['{{ $bloqueId }}']).toString()">
        <span class="flex min-w-0 items-center gap-3">
            <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-90': abiertas['{{ $bloqueId }}'] }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
            </svg>
            <span class="min-w-0">
                <span @class(['block truncate text-sm font-semibold', 'text-gray-900' => ! $historica, 'text-gray-700' => $historica])>{{ $unidad->nombre }}</span>
                <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $unidad->parent ? 'Depende de '.$unidad->parent->nombre : 'Unidad superior de la organización' }}</span>
            </span>
        </span>
        <span class="flex shrink-0 flex-col items-end gap-0.5 text-xs text-gray-500 sm:flex-row sm:items-center sm:gap-3">
            <span>{{ $responsabilidadesUnidad->count() }} {{ $responsabilidadesUnidad->count() === 1 ? 'responsable' : 'responsables' }}</span>
            <span>{{ $responsabilidadesUnidad->where('puede_aprobar', true)->count() }} {{ $responsabilidadesUnidad->where('puede_aprobar', true)->count() === 1 ? 'puede aprobar' : 'pueden aprobar' }}</span>
        </span>
    </button>

    <div x-cloak x-show="abiertas['{{ $bloqueId }}']" class="divide-y divide-gray-100 border-t border-gray-100">
        @foreach ($responsabilidadesUnidad as $responsabilidad)
            <div class="grid gap-3 px-4 py-3 sm:px-5 md:grid-cols-[minmax(12rem,1.4fr)_minmax(12rem,1fr)_auto] md:items-center">
                <div class="min-w-0">
                    <p @class(['truncate text-sm font-medium', 'text-gray-900' => ! $historica, 'text-gray-700' => $historica])>{{ $responsabilidad->persona->nombre_completo }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">RUT {{ $responsabilidad->persona->rut }}</p>
                </div>

                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $responsabilidad->tipo->etiqueta() }}</span>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $historica ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-800' }}">{{ $historica ? 'Histórica' : 'Vigente' }}</span>
                    @if ($responsabilidad->puede_aprobar)
                        <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Puede aprobar</span>
                    @endif
                    <span class="w-full truncate text-xs text-gray-500">{{ $responsabilidad->vigente_desde->format('d/m/Y') }} — {{ $responsabilidad->vigente_hasta?->format('d/m/Y') ?? 'Actualidad' }}</span>
                </div>

                @can('responsabilidades.gestionar')
                    <div class="flex flex-wrap items-end gap-3 md:justify-end">
                        <a href="{{ route('admin.responsabilidades.edit', $responsabilidad) }}" class="pb-2 text-sm font-medium text-indigo-700 hover:underline">Editar</a>

                        @if (! $responsabilidad->vigente_hasta)
                            <form method="POST" action="{{ route('admin.responsabilidades.close', $responsabilidad) }}" class="flex items-end gap-2" onsubmit="return confirm('¿Confirma que desea cerrar esta responsabilidad?')">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-medium text-gray-500">
                                    Término
                                    <input type="date" name="vigente_hasta" min="{{ $responsabilidad->vigente_desde->toDateString() }}" required class="mt-0.5 block w-32 rounded-md border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </label>
                                <button type="submit" class="mb-0.5 text-sm font-medium text-red-700 hover:underline">Cerrar</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>
        @endforeach
    </div>
</article>
