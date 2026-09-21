@props(['current' => 'persona'])

@php
    $steps = [
        'persona' => 'Persona',
        'dotacion' => 'Dotación',
        'acceso' => 'Acceso',
        'finalizar' => 'Finalizar',
    ];
    $currentIndex = array_search($current, array_keys($steps), true);
@endphp

<nav aria-label="Progreso del proceso administrativo" class="overflow-x-auto">
    <ol class="flex min-w-max items-center text-sm">
        @foreach ($steps as $key => $label)
            @php
                $index = array_search($key, array_keys($steps), true);
                $completed = $index < $currentIndex || $current === 'finalizar';
                $active = $key === $current && $current !== 'finalizar';
            @endphp
            <li class="flex items-center">
                @if (! $loop->first)
                    <span class="mx-2 text-gray-300" aria-hidden="true">—</span>
                @endif
                <span @class([
                    'whitespace-nowrap font-medium',
                    'text-green-700' => $completed,
                    'text-indigo-700' => $active,
                    'text-gray-400' => ! $completed && ! $active,
                ])>
                    {{ $label }}@if ($completed) <span aria-hidden="true">✓</span>@endif
                </span>
            </li>
        @endforeach
    </ol>
</nav>
