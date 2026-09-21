@props(['responsabilidades'])

@if ($responsabilidades->isNotEmpty())
    <div class="mt-4 border-t border-gray-200 pt-3">
        <p class="text-xs font-medium text-gray-500">Responsabilidad institucional</p>
        <div class="mt-2 space-y-1.5">
            @foreach ($responsabilidades as $responsabilidad)
                <p class="text-sm text-gray-800">
                    <span class="font-semibold">{{ $responsabilidad->tipo->etiqueta() }}</span>
                    · desde {{ $responsabilidad->vigente_desde->format('d/m/Y') }}
                    @if ($responsabilidad->vigente_hasta)
                        hasta {{ $responsabilidad->vigente_hasta->format('d/m/Y') }}
                    @endif
                </p>
            @endforeach
        </div>
    </div>
@endif
