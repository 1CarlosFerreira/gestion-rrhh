<x-app-layout>
    <x-slot name="header">
        <div><h2 class="text-xl font-semibold text-slate-800">{{ $unidades->count() === 1 && $unidad ? 'Dotación de '.$unidad->nombre : 'Dotación por Unidad/Servicio' }}</h2><p class="mt-1 text-sm text-slate-600">{{ $vinculos?->total() ?? 0 }} {{ ($vinculos?->total() ?? 0) === 1 ? 'funcionario con vínculo vigente' : 'funcionarios con vínculo vigente' }}</p></div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(auth()->user()->can('reemplazos.revisar_personal') && ! auth()->user()->can('reemplazos.crear'))
            <nav class="flex gap-2" aria-label="Personas y dotación">@can('personas.ver')<a href="{{ route('personas.index') }}" class="rounded-full bg-white px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Personas</a>@endcan<a href="{{ route('dotacion.index') }}" class="rounded-full bg-indigo-700 px-3 py-2 text-sm font-medium text-white">Dotación</a></nav>
        @endif
        @if($unidades->isEmpty())
            <section class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm"><p class="font-semibold text-slate-900">No tienes unidades vigentes asignadas.</p><p class="mt-2 text-sm text-slate-600">No es posible consultar dotación hasta que exista una asignación institucional vigente.</p></section>
        @else
            <form method="GET" @class(['grid gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm', 'md:grid-cols-3' => $unidades->count() > 1, 'md:grid-cols-2' => $unidades->count() <= 1])>
                @if($unidades->count() > 1)<select name="unidad_id" aria-label="Unidad o servicio" class="rounded border-gray-300">@foreach($unidades as $item)<option value="{{ $item->id }}" @selected($unidad?->id === $item->id)>{{ $item->nombre }}</option>@endforeach</select>@else<input type="hidden" name="unidad_id" value="{{ $unidad?->id }}"><p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $unidad?->nombre }}</p>@endif
                <x-text-input name="q" :value="request('q')" placeholder="Buscar por RUT, nombre o apellido" aria-label="Buscar por RUT, nombre o apellido"/>
                <div class="flex items-center gap-2"><x-primary-button>Buscar</x-primary-button>@if(request()->filled('q'))<a href="{{ route('dotacion.index', $unidad ? ['unidad_id' => $unidad->id] : []) }}" class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Limpiar búsqueda</a>@endif</div>
            </form>

            @if($vinculos && $vinculos->isNotEmpty())
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Funcionarios">
                    @foreach($vinculos as $vinculo)
                        <article class="flex min-h-64 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200">
                            <div class="flex items-start gap-3"><x-avatar-initials :name="$vinculo->persona->nombre_completo" /><div class="min-w-0"><h3 class="truncate font-semibold text-slate-900">{{ $vinculo->persona->nombre_completo }}</h3><p class="mt-1 text-sm text-slate-600">{{ \App\Support\Rut\Rut::format($vinculo->persona->rut) }}</p></div></div>
                            <div class="mt-5 flex flex-wrap gap-2"><span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Activo</span><span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-800">{{ $vinculo->estamento?->nombre ?? 'No informado' }}</span></div>
                            <dl class="mt-5 space-y-3 text-sm"><div><dt class="text-slate-500">Profesión</dt><dd class="mt-1 text-slate-800">{{ $vinculo->profesion?->nombre ?? 'No informado' }}</dd></div><div><dt class="text-slate-500">Cargo o función</dt><dd class="mt-1 text-slate-800">{{ $vinculo->cargo_texto ?: 'No informado' }}</dd></div></dl>
                            <a href="{{ route('dotacion.show', $vinculo->persona) }}" class="mt-auto pt-5 text-right text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver ficha <span aria-hidden="true">→</span></a>
                        </article>
                    @endforeach
                </section>
                {{ $vinculos->links() }}
            @else
                <section class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm"><span aria-hidden="true" class="text-2xl text-slate-400">⌕</span><h3 class="mt-3 font-semibold text-slate-900">No encontramos funcionarios</h3><p class="mt-2 text-sm text-slate-600">Prueba con otro nombre o RUT, o revisa la unidad seleccionada.</p>@if(request()->filled('q'))<a href="{{ route('dotacion.index', ['unidad_id' => $unidad?->id]) }}" class="mt-4 inline-block font-semibold text-indigo-700">Limpiar búsqueda</a>@endif</section>
            @endif
        @endif
    </div></div>
</x-app-layout>
