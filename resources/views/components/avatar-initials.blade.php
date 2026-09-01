@props(['name', 'size' => 'h-10 w-10'])

<span {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-800 {$size}"]) }} aria-hidden="true">{{ collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</span>
