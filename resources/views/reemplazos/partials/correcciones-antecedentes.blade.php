@php
    $correcciones = $tramite->historial->where('action_code', 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS')->sortBy('id');
    $etiquetasCorreccion = [
        'reemplazante_estamento_id' => 'Estamento',
        'reemplazante_profesion_id' => 'Profesión',
        'reemplazante_calidad_contractual_id' => 'Calidad contractual',
        'reemplazante_cargo_funcion' => 'Cargo / función',
    ];
@endphp
@if($tramite->solicitudContrato && $correcciones->isNotEmpty())
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-950">Historial de correcciones</h2>
        <div class="mt-4 space-y-4">
            @foreach($correcciones as $correccion)
                <article class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
                    <p class="font-medium text-gray-900">{{ $correccion->usuario?->name ?? 'Usuario no disponible' }} · {{ $correccion->occurred_at?->format('d/m/Y H:i') }}</p>
                    <p class="mt-1 text-gray-700">Motivo: {{ $correccion->observation }}</p>
                    <ul class="mt-2 space-y-1 text-gray-700">
                        @foreach(($correccion->metadata['correcciones'] ?? []) as $campo => $cambio)
                            <li>{{ $etiquetasCorreccion[$campo] ?? $campo }}: {{ $cambio['anterior']['nombre'] ?? $cambio['anterior']['valor'] ?? 'No informado' }} → {{ $cambio['nuevo']['nombre'] ?? $cambio['nuevo']['valor'] ?? 'No informado' }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>
@endif
