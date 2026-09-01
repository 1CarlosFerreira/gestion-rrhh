@props(['estado'])

@php
    $codigo = $estado?->codigo ?? '';
    [$label, $classes] = match ($codigo) {
        'BORRADOR' => ['Borrador', 'bg-slate-100 text-slate-700'],
        'DEVUELTA_CORRECCION' => ['Devuelta para corrección', 'bg-amber-100 text-amber-900'],
        'OBSERVADA' => ['Observada', 'bg-amber-100 text-amber-900'],
        'FORMALIZADA' => ['Formalizada', 'bg-emerald-100 text-emerald-900'],
        'ENVIADA_GESTION_PERSONAS' => ['Enviada a Gestión de Personas', 'bg-blue-100 text-blue-900'],
        'EN_REVISION', 'EN_REVISION_JEFATURA', 'PLANILLA_DISPONIBLE' => [$estado->nombre, 'bg-blue-100 text-blue-900'],
        default => [$estado?->nombre ?? 'Sin estado', 'bg-slate-100 text-slate-700'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex w-fit shrink-0 self-start items-center rounded-full px-2.5 py-1 text-xs font-medium leading-none whitespace-nowrap '.$classes]) }}>{{ $label }}</span>
