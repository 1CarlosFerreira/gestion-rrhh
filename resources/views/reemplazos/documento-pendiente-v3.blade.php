<x-app-layout>
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('reemplazos.partials.ficha-base', [
                'volverHref' => $retorno['href'],
                'volverTexto' => $retorno['text'],
                'fechaEtiqueta' => 'Fecha de envío',
                'fechaValor' => $tramite->submitted_at,
            ])
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-semibold text-amber-950">Antecedentes transitorios aprobados</h2>
                <p class="mt-2 text-sm text-amber-900">El respaldo está comprometido y la persona propuesta, si existe, tiene su período reservado. La generación documental V3 aún no está habilitada.</p>
            </section>
            @if($tramite->revisionReemplazo)
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-950">Revisión de Gestión de Personas</h2>
                    <div class="mt-4">@include('reemplazos.partials.revision-lectura')</div>
                </section>
            @endif
            @include('reemplazos.partials.adjuntos')
        </div>
    </div>
</x-app-layout>
