@php($hijos = $unidad->activeChildren)

<li
    x-data="{ open: @js($nivel === 0) }"
    x-on:organigrama-expandir.window="open = $event.detail"
>
    @if ($hijos->isNotEmpty())
        <button type="button" x-on:click="open = ! open" class="mx-auto flex min-h-12 w-full max-w-56 items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left text-sm font-medium shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 {{ $nivel === 0 ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : 'border-gray-200 bg-white text-gray-800' }}" :aria-expanded="open.toString()" aria-label="Expandir o contraer {{ $unidad->nombre }}">
            <span class="min-w-0 break-words">{{ $unidad->nombre }}</span>
            <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.24 4.38a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
        </button>
    @else
        <div class="mx-auto flex min-h-12 w-full max-w-56 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 shadow-sm"><span class="break-words">{{ $unidad->nombre }}</span></div>
    @endif

    @if ($hijos->isNotEmpty())
        <ul x-show="open">
            @foreach ($hijos as $hijo)
                @include('admin.estructura.partials.organigrama-node', ['unidad' => $hijo, 'nivel' => $nivel + 1])
            @endforeach
        </ul>
    @endif
</li>
