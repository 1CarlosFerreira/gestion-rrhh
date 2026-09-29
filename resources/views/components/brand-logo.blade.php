@props([
    'variant' => 'horizontal',
    'alt' => 'Hospital de Illapel Dr. Humberto Elorza Cortés',
])

@php
    $sources = [
        'mark' => 'images/brand/hospital-illapel-mark.png',
        'horizontal' => 'images/brand/hospital-illapel-horizontal.png',
        'vertical' => 'images/brand/hospital-illapel-vertical.png',
    ];

    $source = $sources[$variant] ?? $sources['horizontal'];
@endphp

<img
    src="{{ asset($source) }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'block h-auto max-w-full object-contain']) }}
>
