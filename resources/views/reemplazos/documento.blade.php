<x-app-layout>
    @php
        $documento = $tramite->documentosGenerados->firstWhere('status', 'VIGENTE');
        $listaParaGenerar = $tramite->estadoTramite->codigo === 'LISTA_GENERAR_DOCUMENTO';
        $documentoGenerado = $tramite->estadoTramite->codigo === 'DOCUMENTO_GENERADO';
        $formalizado = $tramite->estadoTramite->codigo === 'FORMALIZADA';
        $detalle = $tramite->reemplazo;
        $revision = $tramite->revisionReemplazo;
        $formalizacion = $tramite->formalizacionReemplazo;
        $formalizacionLegada = $documentoGenerado && !$detalle->tieneAntecedentesLaboralesPropuestos();
    @endphp

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @include('reemplazos.partials.ficha-base', [
                'volverHref' => route('gestion-personas.reemplazos.index'),
                'volverTexto' => 'Volver a Reemplazos',
                'fechaEtiqueta' => $formalizado ? 'Fecha de formalización' : 'Última actualización',
                'fechaValor' => $formalizado ? $formalizacion?->formalizado_at : $tramite->updated_at,
                'mostrarContenido' => !$listaParaGenerar && !$documentoGenerado && !$formalizado,
                'mostrarGenerarDocumento' => $listaParaGenerar,
                'mostrarDescargarDocumento' => $documentoGenerado,
                'documentoDescarga' => $documento,
            ])

            @if($documentoGenerado && $documento && Gate::denies('formalizar-reemplazo', $tramite))
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-950">Documento institucional</h2>
                    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $documento->adjunto->original_name }}</p>
                        <p class="mt-1 text-xs text-gray-500">PDF · Versión {{ $documento->version }}</p>
                        <p class="mt-1 text-xs text-gray-600">Generado el {{ $documento->generated_at->format('d/m/Y H:i') }} por {{ $documento->generadoPor->name }}</p>
                    </div>
                </section>
            @endif

            @if($listaParaGenerar)
                <section aria-labelledby="resumen-generacion-title" class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 id="resumen-generacion-title" class="text-base font-semibold text-gray-950">Resumen para generación</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs text-gray-500">Funcionario</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $detalle->funcionario->nombre_completo }}</dd>
                            <dd class="mt-0.5 font-mono text-xs text-gray-600">{{ $detalle->funcionario->rut }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Reemplazante</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $detalle->reemplazante->nombre_completo }}</dd>
                            <dd class="mt-0.5 font-mono text-xs text-gray-600">{{ $detalle->reemplazante->rut }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Período total</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $detalle->fecha_funcionario_desde->format('d/m/Y') }} al {{ $detalle->fecha_funcionario_hasta->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Cobertura</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $detalle->diasReemplazante() }} días cubiertos</dd>
                            <dd class="mt-0.5 text-xs text-amber-700">{{ $detalle->diasSinCobertura() }} días sin cobertura</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Grado E.U.S.</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $revision?->grado_eus ?? 'No informado' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Clasificación de área</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $revision?->clasificacionArea?->nombre ?? 'No informada' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Cumple normativa</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $revision?->cumple_normativa === null ? 'No informado' : ($revision->cumple_normativa ? 'Sí' : 'No') }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">El documento se generará con los antecedentes aprobados que aparecen en esta ficha.</p>
                </section>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-gray-900 sm:px-6">
                        <span>Ver antecedentes completos</span>
                        <span aria-hidden="true" class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="space-y-5 border-t border-gray-200 p-5 sm:p-6">
                        @include('reemplazos.partials.ficha-base', ['mostrarCabecera' => false])
                    </div>
                </details>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-gray-900 sm:px-6">
                        <span>Ver revisión de Gestión de Personas</span>
                        <span aria-hidden="true" class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="border-t border-gray-200 p-5 sm:p-6">
                        @include('reemplazos.partials.revision-lectura')
                    </div>
                </details>

                @include('reemplazos.partials.adjuntos')
            @elseif($documentoGenerado)
                @can('formalizar-reemplazo', $tramite)
                    <section aria-labelledby="formalizar-reemplazo-title" class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 id="formalizar-reemplazo-title" class="text-lg font-semibold text-gray-950">Formalizar reemplazo</h2>
                        <dl class="mt-4 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="text-xs text-gray-500">Reemplazante</dt><dd class="mt-1 font-semibold text-gray-900">{{ $detalle->reemplazante->nombre_completo }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Unidad</dt><dd class="mt-1 font-medium text-gray-900">{{ $tramite->unidadOrganizacional->nombre }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Período efectivo</dt><dd class="mt-1 font-medium text-gray-900">{{ $detalle->fecha_reemplazante_desde->format('d/m/Y') }} al {{ $detalle->fecha_reemplazante_hasta->format('d/m/Y') }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Documento generado</dt><dd class="mt-1 truncate font-medium text-gray-900">{{ $documento?->adjunto?->original_name ?? 'No disponible' }}</dd>@if($documento)<dd class="mt-0.5 text-xs text-gray-500">PDF · Versión {{ $documento->version }}</dd>@endif</div>
                        </dl>

                        @if($formalizacionLegada && ($estamentos->isEmpty() || $calidades->isEmpty()))
                            <div class="mt-4 rounded bg-amber-50 p-4 text-sm text-amber-900">
                                @if($estamentos->isEmpty())<p>No existen estamentos activos configurados. Debe configurar el catálogo antes de formalizar este reemplazo.</p>@endif
                                @if($calidades->isEmpty())<p>No existen calidades contractuales activas configuradas. Debe configurar el catálogo antes de formalizar este reemplazo.</p>@endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('reemplazos.formalizaciones.store', $tramite) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                                @csrf
                                @if($formalizacionLegada)
                                <fieldset class="grid gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 md:grid-cols-2">
                                    <legend class="px-1 font-semibold text-amber-900">Antecedentes laborales · trámite legado</legend>
                                    <p class="text-sm text-amber-800 md:col-span-2">Este documento fue generado antes de incorporar la propuesta laboral a la solicitud. Complete los antecedentes para finalizarlo.</p>
                                    <label class="block text-sm">Estamento *
                                        <select name="estamento_id" required class="mt-1 block w-full rounded-md border-slate-300">
                                            <option value="">Seleccione</option>
                                            @foreach($estamentos as $estamento)<option value="{{ $estamento->id }}" @selected(old('estamento_id') == $estamento->id)>{{ $estamento->nombre }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm">Profesión
                                        <select name="profesion_id" class="mt-1 block w-full rounded-md border-slate-300">
                                            <option value="">Sin profesión informada</option>
                                            @foreach($profesiones as $profesion)<option value="{{ $profesion->id }}" @selected(old('profesion_id') == $profesion->id)>{{ $profesion->nombre }}@if($profesion->estamento) · {{ $profesion->estamento->nombre }}@endif</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm">Calidad contractual *
                                        <select name="calidad_contractual_id" required class="mt-1 block w-full rounded-md border-slate-300">
                                            <option value="">Seleccione</option>
                                            @foreach($calidades as $calidad)<option value="{{ $calidad->id }}" @selected(old('calidad_contractual_id', $calidadReemplazoId) == $calidad->id)>{{ $calidad->nombre }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm">Cargo / función *
                                        <input name="cargo_funcion" value="{{ old('cargo_funcion') }}" required maxlength="200" class="mt-1 block w-full rounded-md border-slate-300">
                                    </label>
                                    <div class="block text-sm"><span>Grado EUS</span><p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">{{ $revision?->grado_eus ?? 'No informado' }}</p><p class="mt-1 text-xs text-slate-500">Valor registrado durante la revisión de Gestión de Personas.</p></div>
                                </fieldset>
                                @else
                                <fieldset class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
                                    <legend class="px-1 font-semibold text-slate-700">Antecedentes propuestos del reemplazante</legend>
                                    <div class="text-sm"><span class="text-gray-500">Estamento</span><p class="font-medium text-gray-900">{{ $detalle->reemplazanteEstamento->nombre }}</p></div>
                                    <div class="text-sm"><span class="text-gray-500">Profesión</span><p class="font-medium text-gray-900">{{ $detalle->reemplazanteProfesion?->nombre ?? 'No corresponde / no informada' }}</p></div>
                                    <div class="text-sm"><span class="text-gray-500">Calidad contractual</span><p class="font-medium text-gray-900">{{ $detalle->reemplazanteCalidadContractual->nombre }}</p></div>
                                    <div class="text-sm"><span class="text-gray-500">Cargo / función</span><p class="font-medium text-gray-900">{{ $detalle->reemplazante_cargo_funcion }}</p></div>
                                    <div class="text-sm"><span class="text-gray-500">Grado EUS</span><p class="font-medium text-gray-900">{{ $revision?->grado_eus ?? 'No informado' }}</p></div>
                                </fieldset>
                                @endif

                                <fieldset class="grid gap-4 md:grid-cols-2">
                                    <legend class="mb-3 font-semibold text-slate-700">Formalización</legend>
                                    <label class="block text-sm">Identificador externo<input name="identificador_externo" value="{{ old('identificador_externo') }}" maxlength="255" class="mt-1 block w-full rounded-md border-slate-300"></label>
                                    <label class="block text-sm">Documento final opcional<input type="file" name="documento_final" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm"></label>
                                    <label class="block text-sm md:col-span-2">Observación<textarea name="observacion" maxlength="5000" rows="3" class="mt-1 block w-full rounded-md border-slate-300">{{ old('observacion') }}</textarea></label>
                                </fieldset>

                                <p class="rounded bg-indigo-50 p-4 text-sm text-indigo-900">Al formalizar, el reemplazante será incorporado a la dotación de esta unidad por el período efectivo indicado.</p>
                                <x-primary-button>Confirmar formalización</x-primary-button>
                            </form>
                        @endif
                    </section>
                @endcan

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-gray-900 sm:px-6">
                        <span>Ver antecedentes del trámite y cobertura</span>
                        <span aria-hidden="true" class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="space-y-5 border-t border-gray-200 p-5 sm:p-6">
                        @include('reemplazos.partials.ficha-base', ['mostrarCabecera' => false])
                    </div>
                </details>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-gray-900 sm:px-6">
                        <span>Ver revisión de Gestión de Personas</span>
                        <span aria-hidden="true" class="text-gray-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="border-t border-gray-200 p-5 sm:p-6">
                        @include('reemplazos.partials.revision-lectura')
                    </div>
                </details>

                @include('reemplazos.partials.adjuntos', ['excluirAdjuntoIds' => $documento ? [$documento->adjunto_id] : []])
            @elseif($formalizado)
                @php
                    $idsDocumentosPrincipales = array_values(array_filter([$documento?->adjunto_id, $formalizacion?->adjunto_id]));
                    $otrosAdjuntos = $tramite->adjuntos->whereNotIn('id', $idsDocumentosPrincipales);
                    $diasTotales = $detalle->diasFuncionario();
                    $diasCubiertos = $detalle->diasReemplazante();
                    $diasSinCobertura = $detalle->diasSinCobertura();
                @endphp

                <section aria-labelledby="resumen-final-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 id="resumen-final-title" class="text-base font-semibold text-gray-950">Resumen del reemplazo</h2>
                    <div class="mt-4 flex flex-col gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 sm:flex-row sm:items-center sm:gap-3">
                        <div class="min-w-0 flex-1"><p class="text-xs text-indigo-600">Funcionario reemplazado</p><p class="truncate text-sm font-semibold text-indigo-950">{{ $detalle->funcionario->nombre_completo }}</p></div>
                        <span class="text-lg text-indigo-400" aria-hidden="true">→</span>
                        <div class="min-w-0 flex-1"><p class="text-xs text-indigo-600">Reemplazante</p><p class="truncate text-sm font-semibold text-indigo-950">{{ $detalle->reemplazante->nombre_completo }}</p></div>
                    </div>
                    <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs text-gray-500">Tipo de reemplazo</dt><dd class="mt-1 font-medium text-gray-900">{{ $detalle->tipoReemplazo->nombre }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Período total solicitado</dt><dd class="mt-1 font-medium text-gray-900">{{ $detalle->fecha_funcionario_desde->format('d/m/Y') }} al {{ $detalle->fecha_funcionario_hasta->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Período efectivo</dt><dd class="mt-1 font-medium text-gray-900">{{ $detalle->fecha_reemplazante_desde->format('d/m/Y') }} al {{ $detalle->fecha_reemplazante_hasta->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Cobertura</dt><dd class="mt-1 font-medium text-gray-900">{{ $diasCubiertos }} de {{ $diasTotales }} días</dd><dd class="mt-0.5 text-xs text-gray-500">{{ $diasSinCobertura }} días sin cobertura</dd></div>
                    </dl>
                </section>

                <section aria-labelledby="documentos-finales-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 id="documentos-finales-title" class="text-base font-semibold text-gray-950">Documentos</h2>
                    <div class="mt-4 space-y-3">
                        @if($documento)
                            <div class="flex flex-col gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Solicitud PDF generada por el sistema</p><p class="mt-1 truncate text-sm font-semibold text-gray-900">{{ $documento->adjunto->original_name }}</p><p class="mt-0.5 text-xs text-gray-500">Versión {{ $documento->version }} · {{ $documento->generated_at->format('d/m/Y H:i') }}</p></div>
                                @can('generar-documento-reemplazo', $tramite)<a href="{{ route('reemplazos.documentos.download', [$tramite, $documento]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-700 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Descargar</a>@endcan
                            </div>
                        @endif
                        @if($formalizacion?->adjunto)
                            <div class="flex flex-col gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Documento final firmado / DocDigital</p><p class="mt-1 truncate text-sm font-semibold text-gray-900">{{ $formalizacion->adjunto->original_name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $formalizacion->adjunto->tipoDocumento?->nombre ?? 'Respaldo de formalización' }} · versión {{ $formalizacion->adjunto->version }}</p></div>
                                @can('tramites.adjuntos.descargar')<a href="{{ route('reemplazos.adjuntos.download', [$tramite, $formalizacion->adjunto]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Descargar</a>@endcan
                            </div>
                        @endif
                        @foreach($otrosAdjuntos as $adjunto)
                            <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $adjunto->tipoDocumento?->nombre ?? 'Antecedente adjunto' }}</p><p class="mt-1 truncate text-sm font-semibold text-gray-900">{{ $adjunto->original_name }}</p><p class="mt-0.5 text-xs text-gray-500">Versión {{ $adjunto->version }}</p></div>
                                @can('tramites.adjuntos.descargar')<a href="{{ route('reemplazos.adjuntos.download', [$tramite, $adjunto]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Descargar</a>@endcan
                            </div>
                        @endforeach
                    </div>
                </section>

                <section aria-labelledby="formalizacion-registrada-title" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 id="formalizacion-registrada-title" class="text-base font-semibold text-gray-950">Formalización registrada</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-xs text-gray-500">Estamento</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->estamento->nombre }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Profesión</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->profesion?->nombre ?? 'No informada' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Calidad contractual</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->calidadContractual->nombre }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Cargo / función</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->cargo_funcion }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Grado EUS</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->grado_eus ?? 'No informado' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Registrado por</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->formalizadoPor->name }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Fecha</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->formalizado_at->format('d/m/Y H:i') }}</dd></div>
                        @if($formalizacion->identificador_externo)<div><dt class="text-xs text-gray-500">Referencia externa</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->identificador_externo }}</dd></div>@endif
                        @if($formalizacion->adjunto)<div><dt class="text-xs text-gray-500">Respaldo final</dt><dd class="mt-1 font-medium text-gray-900">{{ $formalizacion->adjunto->original_name }}</dd></div>@endif
                    </dl>
                </section>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-gray-900 sm:px-6"><span>Ver antecedentes y revisión</span><span aria-hidden="true" class="text-gray-400 transition group-open:rotate-180">⌄</span></summary>
                    <div class="space-y-5 border-t border-gray-200 p-5 sm:p-6">
                        @include('reemplazos.partials.ficha-base', ['mostrarCabecera' => false])
                        @include('reemplazos.partials.revision-lectura')
                    </div>
                </details>
            @else
                @include('reemplazos.partials.adjuntos')
                @include('reemplazos.partials.revision-lectura')
            @endif

            @if(!$listaParaGenerar && !$documentoGenerado && !$formalizado)
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950">Documento institucional</h2>
                        <p class="mt-1 text-sm text-gray-500">Solicitud de Reemplazo de Personal</p>
                    </div>
                </div>

                @if($documento)
                    <div class="mt-4 flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $documento->adjunto->original_name }}</p>
                            <p class="mt-1 text-xs text-gray-500">PDF · Versión {{ $documento->version }}</p>
                            <p class="mt-1 text-xs text-gray-600">Generado el {{ $documento->generated_at->format('d/m/Y H:i') }} por {{ $documento->generadoPor->name }}</p>
                        </div>
                        @can('generar-documento-reemplazo', $tramite)
                            <a href="{{ route('reemplazos.documentos.download', [$tramite, $documento]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-600">Descargar PDF</a>
                        @endcan
                    </div>
                @endif
            </section>
            @endif

            @if($tramite->formalizacionReemplazo && !$formalizado)
                @php($formalizacion = $tramite->formalizacionReemplazo)
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-950">Formalización registrada</h2>
                    <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div><dt class="text-slate-500">Estamento</dt><dd>{{ $formalizacion->estamento->nombre }}</dd></div>
                        <div><dt class="text-slate-500">Profesión</dt><dd>{{ $formalizacion->profesion?->nombre ?? 'No informada' }}</dd></div>
                        <div><dt class="text-slate-500">Calidad contractual</dt><dd>{{ $formalizacion->calidadContractual->nombre }}</dd></div>
                        <div><dt class="text-slate-500">Cargo / función</dt><dd>{{ $formalizacion->cargo_funcion }}</dd></div>
                        <div><dt class="text-slate-500">Grado EUS</dt><dd>{{ $formalizacion->grado_eus ?? 'No informado' }}</dd></div>
                        <div><dt class="text-slate-500">Referencia externa</dt><dd>{{ $formalizacion->identificador_externo ?? 'No informada' }}</dd></div>
                        <div><dt class="text-slate-500">Registrada por</dt><dd>{{ $formalizacion->formalizadoPor->name }} · {{ $formalizacion->formalizado_at->format('d/m/Y H:i') }}</dd></div>
                        <div><dt class="text-slate-500">Respaldo final</dt><dd>{{ $formalizacion->adjunto?->original_name ?? 'No adjuntado' }}</dd></div>
                    </dl>
                    @if($formalizacion->observacion)<p class="mt-4 text-sm"><span class="text-slate-500">Observación:</span> {{ $formalizacion->observacion }}</p>@endif
                    <p class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">El reemplazante fue incorporado a la dotación por el período efectivo del reemplazo.</p>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
