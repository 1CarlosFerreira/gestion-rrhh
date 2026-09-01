<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Inicio
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section>
                <h1 class="text-2xl font-semibold text-slate-900">Bienvenido/a, {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-slate-600">Gestiona las solicitudes y pendientes de tu unidad.</p>
            </section>

            @can('reemplazos.crear')
                <section class="grid gap-4 md:grid-cols-2" aria-label="Accesos rápidos">
                    <article class="rounded-xl border border-indigo-100 bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-3"><span aria-hidden="true" class="grid h-10 w-10 place-items-center rounded-lg bg-indigo-100 text-indigo-800">↔</span><div><h2 class="text-lg font-semibold text-slate-900">Solicitud de Reemplazo</h2><p class="mt-1 text-sm text-slate-600">Solicita la cobertura de una ausencia o cargo vacante.</p></div></div>
                        <a href="{{ route('reemplazos.create') }}" class="mt-5 inline-flex rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Solicitar reemplazo</a>
                    </article>
                    <article class="rounded-xl border border-sky-100 bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-3"><span aria-hidden="true" class="grid h-10 w-10 place-items-center rounded-lg bg-sky-100 text-sky-800">◷</span><div><h2 class="text-lg font-semibold text-slate-900">Horas Extraordinarias</h2><p class="mt-1 text-sm text-slate-600">Solicita y revisa las planillas mensuales de tu unidad.</p></div></div>
                        <a href="{{ route('horas-extra.create') }}" class="mt-5 inline-flex rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">Solicitar horas extraordinarias</a>
                    </article>
                </section>
            @endcan

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumen de trámites">
                @foreach([
                    ['Borradores', $drafts, 'borradores', 'bg-slate-100 text-slate-700'],
                    ['En revisión', $inReview, 'en_revision', 'bg-blue-100 text-blue-800'],
                    ['Devueltos para corrección', $returned, 'devueltos', 'bg-amber-100 text-amber-900'],
                    ['Formalizados', $formalized, 'formalizados', 'bg-emerald-100 text-emerald-900'],
                ] as [$label, $value, $filter, $accent])
                    <a href="{{ route('tramites.index', [$filter => true]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span aria-hidden="true" class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $accent }}">{{ $label }}</span>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $value }}</p>
                    </a>
                @endforeach
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4"><div><h2 class="text-lg font-semibold text-slate-900">Requieren mi atención</h2><p class="mt-1 text-sm text-slate-600">Solicitudes donde puedes continuar una acción pendiente.</p></div></div>
                <div class="mt-4 space-y-3">
                    @forelse($attention as $tramite)
                        @php($isReturned = $tramite->estadoTramite->codigo === 'DEVUELTA_CORRECCION')
                        @php($action = $isReturned ? 'Corregir' : 'Revisar')
                        @php($url = $isReturned && $tramite->tipoTramite->codigo === 'REEMPLAZO' ? route('reemplazos.edit', $tramite) : route('tramites.show', $tramite))
                        <article class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4">
                            <div><p class="font-medium text-slate-900">{{ $tramite->codigo }} · {{ $tramite->tipoTramite->nombre }}</p><p class="mt-1 text-sm text-slate-600">{{ $tramite->unidadServicio->nombre }} · Actualizado {{ $tramite->updated_at->format('d-m-Y H:i') }}</p></div>
                            <div class="flex items-center gap-3"><x-status-badge :estado="$tramite->estadoTramite" /><a href="{{ $url }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">{{ $action }}</a></div>
                        </article>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No tienes trámites pendientes de acción.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-slate-900">Trámites recientes</h2><a href="{{ route('tramites.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver todos mis trámites</a></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recent as $tramite)
                        <a href="{{ route('tramites.show', $tramite) }}" class="flex items-center justify-between gap-4 py-3 hover:bg-slate-50"><div><p class="font-medium text-slate-900">{{ $tramite->codigo }} · {{ $tramite->tipoTramite->nombre }}</p><p class="mt-1 text-sm text-slate-600">Actualizado {{ $tramite->updated_at->format('d-m-Y H:i') }}</p></div><x-status-badge :estado="$tramite->estadoTramite" /></a>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Aún no has creado trámites.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
