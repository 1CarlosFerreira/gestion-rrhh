@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand-primary text-start text-base font-medium text-brand-primary-dark bg-brand-primary-soft focus:outline-none focus:text-brand-primary-dark focus:bg-brand-primary-soft focus:border-brand-primary-dark transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-brand-primary-dark hover:bg-brand-primary-soft hover:border-brand-primary focus:outline-none focus:text-brand-primary-dark focus:bg-brand-primary-soft focus:border-brand-primary transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
