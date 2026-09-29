@props(['name', 'size' => 'h-10 w-10'])

<span {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full bg-brand-primary-soft text-sm font-semibold text-brand-primary-dark {$size}"]) }} aria-hidden="true">{{ collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</span>
