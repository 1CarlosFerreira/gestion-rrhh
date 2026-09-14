@php
    $usuario = $accesosUsuario->first()->user;
    $accesosVigentes = $accesosUsuario->filter(fn ($acceso) => $acceso->estaVigenteEn($fecha));
    $accesosHistoricos = $accesosUsuario->reject(fn ($acceso) => $acceso->estaVigenteEn($fecha));
    $unidadesAlcanzadas = $service->unidadesAccesibles($usuario, $fecha);
@endphp

<article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <button type="button" class="flex w-full items-center justify-between gap-4 bg-gray-50/70 px-4 py-3 text-left transition hover:bg-gray-100 sm:px-5" x-on:click="cambiar('{{ $bloqueId }}')" x-bind:aria-expanded="Boolean(abiertos['{{ $bloqueId }}']).toString()">
        <span class="flex min-w-0 items-center gap-3">
            <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-90': abiertos['{{ $bloqueId }}'] }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" /></svg>
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-gray-900">{{ $usuario->name }}</span>
                <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $usuario->email }}@if ($usuario->persona?->rut) · RUT {{ $usuario->persona->rut }}@endif</span>
            </span>
        </span>
        <span class="shrink-0 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">{{ $accesosVigentes->count() }} {{ $accesosVigentes->count() === 1 ? 'acceso vigente' : 'accesos vigentes' }}</span>
    </button>

    <div x-cloak x-show="abiertos['{{ $bloqueId }}']" class="border-t border-gray-100">
        @if ($accesosVigentes->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach ($accesosVigentes as $acceso)
                    @include('admin.accesos.partials.access-row', ['acceso' => $acceso, 'historico' => false, 'unidadesAlcanzadas' => $unidadesAlcanzadas])
                @endforeach
            </div>
        @endif
        @if ($accesosHistoricos->isNotEmpty())
            <div class="border-t border-gray-200 bg-gray-50/80">
                <p class="px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-5">Históricos</p>
                <div class="divide-y divide-gray-200">
                    @foreach ($accesosHistoricos as $acceso)
                        @include('admin.accesos.partials.access-row', ['acceso' => $acceso, 'historico' => true, 'unidadesAlcanzadas' => $unidadesAlcanzadas])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</article>
