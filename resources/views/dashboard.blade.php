<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Inicio
        </h2>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div><h1 class="text-2xl font-semibold">Bienvenido/a, {{ auth()->user()->name }}</h1><p class="text-gray-600">Gestiona tus solicitudes y revisa los trámites que requieren atención.</p></div>
        @can('reemplazos.crear')<section class="grid gap-4 md:grid-cols-2"><article class="rounded-lg bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Nueva solicitud de reemplazo</h2><p class="my-3 text-sm text-gray-600">Inicia una solicitud para cubrir una ausencia o necesidad de reemplazo.</p><a href="{{ route('reemplazos.create') }}" class="inline-block rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Crear solicitud</a></article><article class="rounded-lg bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Horas extraordinarias</h2><p class="my-3 text-sm text-gray-600">Solicita y gestiona planillas de horas extraordinarias de tu unidad.</p><a href="{{ route('horas-extra.create') }}" class="inline-block rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Crear solicitud</a></article></section>@endcan
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach([['Trámites activos',$active],['Devueltos para corrección',$returned],['Pendientes de mi acción',$attention->count()],['Formalizados',$formalized]] as [$label,$value])<article class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-600">{{ $label }}</p><p class="mt-1 text-3xl font-semibold">{{ $value }}</p></article>@endforeach</section>
        <section class="rounded-lg bg-white p-5 shadow-sm"><h2 class="font-semibold">Requieren mi atención</h2><div class="mt-3 space-y-2">@forelse($attention as $tramite)<a href="{{ route('tramites.show', $tramite) }}" class="flex flex-wrap items-center justify-between gap-2 rounded border p-3 hover:bg-gray-50"><span><strong>{{ $tramite->codigo }}</strong> · {{ $tramite->tipoTramite->nombre }} · {{ $tramite->unidadServicio->nombre }}</span><span class="rounded-full bg-amber-100 px-3 py-1 text-xs text-amber-900">{{ $tramite->estadoTramite->nombre }}</span></a>@empty<p class="text-sm text-gray-500">No tienes trámites pendientes de acción.</p>@endforelse</div></section>
    </div></div>
</x-app-layout>
