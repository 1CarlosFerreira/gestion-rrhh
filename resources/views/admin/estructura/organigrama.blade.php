@php
    $nodos = [];
    $recorrer = function ($unidad, array $ruta = []) use (&$recorrer, &$nodos): void {
        $hijos = $unidad->activeChildren;
        $rutaActual = [...$ruta, ['id' => $unidad->id, 'nombre' => $unidad->nombre]];
        $nodos[$unidad->id] = [
            'id' => $unidad->id,
            'nombre' => $unidad->nombre,
            'hijos' => $hijos->pluck('id')->values()->all(),
            'ruta' => $rutaActual,
        ];

        foreach ($hijos as $hijo) {
            $recorrer($hijo, $rutaActual);
        }
    };

    foreach ($raices as $raiz) {
        $recorrer($raiz);
    }
@endphp

<x-app-layout>
    <div class="fixed inset-0 z-50 flex min-h-0 flex-col bg-gray-100" x-data="{ nodos: @js($nodos), seleccionado: null, get actual() { return this.seleccionado ? this.nodos[this.seleccionado] : null }, abrir(id) { if (this.nodos[id]?.hijos.length) this.seleccionado = id } }">
        <header class="shrink-0 border-b border-gray-200 bg-white px-4 py-3 shadow-sm sm:px-6">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-3">
                <h1 class="text-lg font-semibold text-gray-900">Organigrama</h1>
                <nav class="min-w-0 flex-1" aria-label="Ruta de la rama actual">
                    <div x-show="! actual" class="text-sm text-gray-500">Vista general</div>
                    <ol x-show="actual" class="flex min-w-0 flex-wrap items-center gap-1 text-sm">
                        <template x-for="(item, indice) in actual?.ruta ?? []" :key="item.id">
                            <li class="flex min-w-0 items-center gap-1">
                                <span x-show="indice > 0" class="text-gray-400" aria-hidden="true">›</span>
                                <button type="button" x-on:click="seleccionado = item.id" class="max-w-64 truncate text-indigo-700 hover:underline" x-text="item.nombre"></button>
                            </li>
                        </template>
                    </ol>
                </nav>
                <div class="ml-auto flex shrink-0 gap-2">
                    <button type="button" x-show="actual?.ruta.length > 1" x-on:click="seleccionado = actual.ruta[actual.ruta.length - 2].id" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">← Volver</button>
                    <button type="button" x-on:click="seleccionado = null" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">Vista general</button>
                    <a href="{{ route('admin.estructura.index') }}" class="inline-flex items-center rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-700">Cerrar</a>
                </div>
            </div>
        </header>

        <main class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="mx-auto flex min-h-full max-w-7xl items-center justify-center">
                @if ($raices->isEmpty())
                    <p class="text-sm text-gray-500">No hay una estructura institucional activa cargada.</p>
                @else
                    <section x-show="! actual" class="w-full space-y-10" aria-label="Vista general del organigrama">
                        @foreach ($raices as $raiz)
                            <div class="mx-auto w-full">
                                <div class="mx-auto flex min-h-14 w-full max-w-xs items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-5 py-3 text-center font-semibold text-indigo-900 shadow-sm">{{ $raiz->nombre }}</div>
                                @if ($raiz->activeChildren->isNotEmpty())
                                    <div class="mx-auto h-8 w-px bg-gray-300" aria-hidden="true"></div>
                                    <div class="relative grid grid-cols-1 gap-4 border-t border-gray-300 pt-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        @foreach ($raiz->activeChildren as $hijo)
                                            <div class="relative flex justify-center before:absolute before:-top-8 before:left-1/2 before:h-8 before:w-px before:bg-gray-300">
                                                @if ($hijo->activeChildren->isNotEmpty())
                                                    <button type="button" x-on:click="abrir({{ $hijo->id }})" class="flex min-h-14 w-full max-w-xs items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-800 shadow-sm hover:border-indigo-300 hover:bg-indigo-50"><span class="break-words">{{ $hijo->nombre }}</span><span class="shrink-0 text-indigo-600" aria-hidden="true">›</span></button>
                                                @else
                                                    <div class="flex min-h-14 w-full max-w-xs items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-sm font-medium text-gray-800 shadow-sm">{{ $hijo->nombre }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </section>

                    <section x-show="actual" class="w-full" aria-label="Rama seleccionada del organigrama">
                        <div class="mx-auto flex min-h-14 w-full max-w-sm items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-5 py-3 text-center font-semibold text-indigo-900 shadow-sm" x-text="actual?.nombre"></div>
                        <template x-if="actual?.hijos.length">
                            <div>
                                <div class="mx-auto h-8 w-px bg-gray-300" aria-hidden="true"></div>
                                <div class="relative grid grid-cols-1 gap-4 border-t border-gray-300 pt-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    <template x-for="hijoId in actual.hijos" :key="hijoId">
                                        <div class="relative flex justify-center before:absolute before:-top-8 before:left-1/2 before:h-8 before:w-px before:bg-gray-300">
                                            <button type="button" x-on:click="abrir(hijoId)" class="flex min-h-14 w-full max-w-xs items-center justify-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-sm font-medium text-gray-800 shadow-sm" :class="nodos[hijoId].hijos.length ? 'hover:border-indigo-300 hover:bg-indigo-50' : 'cursor-default'"><span class="break-words" x-text="nodos[hijoId].nombre"></span><span x-show="nodos[hijoId].hijos.length" class="shrink-0 text-indigo-600" aria-hidden="true">›</span></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p x-show="actual && ! actual.hijos.length" class="mt-6 text-center text-sm text-gray-500">Esta unidad no tiene unidades dependientes.</p>
                    </section>
                @endif
            </div>
        </main>
    </div>
</x-app-layout>
