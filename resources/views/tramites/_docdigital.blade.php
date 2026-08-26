@php
    $registroActual = $tramite->registrosDocDigital->firstWhere('is_current', true);
    $preparado = in_array($tramite->estadoTramite->codigo, ['INFORME_TECNICO_GENERADO', 'DOCUMENTO_GENERADO', 'ENVIADA_DOCDIGITAL'], true);
    $generadosVigentes = $tramite->documentosGenerados->where('status', 'VIGENTE');
    $idsGenerados = $generadosVigentes->pluck('adjunto_id');
    $otrosAdjuntos = $tramite->adjuntos->where('status', 'ACTIVO')->whereNotIn('id', $idsGenerados);
@endphp
<section class="rounded-lg bg-white p-5 shadow-sm">
    <h3 class="font-semibold">DocDigital</h3>
    @if(! $registroActual)
        <p class="mt-2">Estado: <strong>Pendiente de envío</strong></p>
        @if($tramite->tipoTramite->codigo === 'REEMPLAZO' && $tramite->estadoTramite->codigo === 'LISTA_GENERAR_DOCUMENTO')
            <p class="mt-2 rounded bg-amber-50 p-3 text-amber-800">Plantilla institucional pendiente de validación por RRHH.</p>
        @endif
    @elseif($registroActual->estado === 'ENVIADO')
        <p class="mt-2">Estado: <strong>Enviado a DocDigital</strong></p>
    @else
        <p class="mt-2">Estado: <strong>Formalizado</strong></p>
    @endif

    @can('docdigital.registrar_envio')
        @if($preparado && $registroActual?->estado !== 'FORMALIZADO' && ($generadosVigentes->isNotEmpty() || $otrosAdjuntos->isNotEmpty()))
            <details class="mt-4"><summary class="cursor-pointer font-medium text-blue-700">{{ $registroActual ? 'Registrar reenvío a DocDigital' : 'Registrar envío a DocDigital' }}</summary>
                <form method="POST" action="{{ route('tramites.docdigital.store', $tramite) }}" class="mt-3 grid gap-3 md:grid-cols-2">@csrf
                    <label class="md:col-span-2">Documento enviado *<select name="adjunto_enviado_id" required class="mt-1 w-full rounded border-gray-300">
                        @if($generadosVigentes->isNotEmpty())<optgroup label="Documentos generados vigentes">@foreach($generadosVigentes as $documento)<option value="{{ $documento->adjunto_id }}">{{ $documento->tipoDocumento->nombre }} · v{{ $documento->version }} · {{ $documento->adjunto->original_name }}</option>@endforeach</optgroup>@endif
                        @if($otrosAdjuntos->isNotEmpty())<optgroup label="Otros adjuntos vigentes">@foreach($otrosAdjuntos as $adjunto)<option value="{{ $adjunto->id }}">{{ $adjunto->tipoDocumento?->nombre ?? 'Sin tipo' }} · {{ $adjunto->original_name }}</option>@endforeach</optgroup>@endif
                    </select></label>
                    <label>Fecha de envío *<input type="datetime-local" name="fecha_envio" value="{{ old('fecha_envio', now()->format('Y-m-d\TH:i')) }}" required class="mt-1 w-full rounded border-gray-300"></label>
                    <label>Identificador DocDigital<input name="identificador_externo" value="{{ old('identificador_externo') }}" maxlength="190" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="md:col-span-2">Observación<textarea name="observacion_envio" class="mt-1 w-full rounded border-gray-300">{{ old('observacion_envio') }}</textarea></label>
                    <x-primary-button>Registrar envío</x-primary-button>
                </form>
            </details>
        @endif
    @endcan

    @if($registroActual)
        <div class="mt-4 rounded border p-3"><p><strong>Intento {{ $registroActual->intento }}</strong> · {{ $registroActual->fecha_envio->format('d-m-Y H:i') }}</p><p class="text-sm">Documento enviado: {{ $registroActual->adjuntoEnviado->original_name }} · registrado por {{ $registroActual->registradoPor->name }}</p><p class="text-sm">Identificador: {{ $registroActual->identificador_externo ?? 'No informado' }}</p>@if($registroActual->observacion_envio)<p class="text-sm">{{ $registroActual->observacion_envio }}</p>@endif
            @if($registroActual->estado === 'FORMALIZADO')<div class="mt-2 border-t pt-2"><p>Formalizado el {{ $registroActual->fecha_formalizacion->format('d-m-Y H:i') }} por {{ $registroActual->formalizadoPor->name }}</p><p class="text-sm">Documento final: {{ $registroActual->adjuntoFinal->original_name }}</p>@can('tramites.adjuntos.descargar')<a class="text-blue-700" href="{{ route('tramites.adjuntos.download', [$tramite, $registroActual->adjuntoFinal]) }}">Descargar documento final</a>@endcan</div>@endif
        </div>
    @endif

    @can('docdigital.registrar_formalizacion')
        @if($registroActual?->estado === 'ENVIADO' && $tramite->estadoTramite->codigo === 'ENVIADA_DOCDIGITAL')
            <details class="mt-4"><summary class="cursor-pointer font-medium text-blue-700">Registrar formalización</summary><form method="POST" enctype="multipart/form-data" action="{{ route('tramites.docdigital.formalize', $tramite) }}" class="mt-3 grid gap-3 md:grid-cols-2">@csrf
                <label>Fecha de formalización *<input type="datetime-local" name="fecha_formalizacion" value="{{ old('fecha_formalizacion', now()->format('Y-m-d\TH:i')) }}" required class="mt-1 w-full rounded border-gray-300"></label>
                <label>Identificador DocDigital<input name="identificador_externo" value="{{ old('identificador_externo', $registroActual->identificador_externo) }}" maxlength="190" class="mt-1 w-full rounded border-gray-300"></label>
                <label class="md:col-span-2">Documento final *<input type="file" name="archivo_final" required class="mt-1 w-full rounded border p-2"></label>
                <label class="md:col-span-2">Observación<textarea name="observacion_formalizacion" class="mt-1 w-full rounded border-gray-300">{{ old('observacion_formalizacion') }}</textarea></label>
                <x-primary-button>Registrar formalización</x-primary-button>
            </form></details>
        @endif
    @endcan

    @if($tramite->registrosDocDigital->count() > 1)<details class="mt-4"><summary class="cursor-pointer text-sm text-blue-700">Intentos anteriores</summary><div class="mt-2 space-y-2">@foreach($tramite->registrosDocDigital->where('is_current', false) as $registro)<p class="rounded border p-2 text-sm">Intento {{ $registro->intento }} · {{ $registro->fecha_envio->format('d-m-Y H:i') }} · {{ $registro->adjuntoEnviado->original_name }} · {{ $registro->registradoPor->name }}</p>@endforeach</div></details>@endif
</section>
