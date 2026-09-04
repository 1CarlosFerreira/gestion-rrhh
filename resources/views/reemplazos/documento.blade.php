<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Documento institucional · {{ $tramite->codigo }}</h2>
        <p class="text-sm text-slate-500">{{ $tramite->unidadOrganizacional->nombre }} · {{ $tramite->estadoTramite->nombre }}</p>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-5 px-4">
            @if(session('status'))
                <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <section class="rounded-xl bg-white p-6 shadow">
                    <h3 class="font-semibold">Funcionario reemplazado</h3>
                    <p class="mt-2">{{ $tramite->reemplazo->funcionario->nombre_completo }} · {{ $tramite->reemplazo->funcionario->rut }}</p>
                    <p>{{ $tramite->reemplazo->fecha_funcionario_desde->format('d/m/Y') }} al {{ $tramite->reemplazo->fecha_funcionario_hasta->format('d/m/Y') }}</p>
                </section>
                <section class="rounded-xl bg-white p-6 shadow">
                    <h3 class="font-semibold">Reemplazante</h3>
                    <p class="mt-2">{{ $tramite->reemplazo->reemplazante->nombre_completo }} · {{ $tramite->reemplazo->reemplazante->rut }}</p>
                    <p>{{ $tramite->reemplazo->fecha_reemplazante_desde->format('d/m/Y') }} al {{ $tramite->reemplazo->fecha_reemplazante_hasta->format('d/m/Y') }}</p>
                </section>
            </div>

            <section class="rounded-xl bg-white p-6 shadow">
                <h3 class="font-semibold">Documento generado</h3>
                @if($documento = $tramite->documentosGenerados->firstWhere('status', 'VIGENTE'))
                    <p class="mt-3">Solicitud de Reemplazo · Versión {{ $documento->version }}</p>
                    <p class="text-sm text-slate-600">Generado: {{ $documento->generated_at->format('d/m/Y H:i') }} · Por: {{ $documento->generadoPor->name }}</p>
                    @can('generar-documento-reemplazo', $tramite)
                        <a href="{{ route('reemplazos.documentos.download', [$tramite, $documento]) }}" class="mt-4 inline-block rounded bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">Descargar PDF</a>
                    @endcan
                @else
                    @can('generar-documento-reemplazo', $tramite)
                        <p class="mt-2 text-sm text-slate-600">Se generará el documento institucional con los antecedentes actualmente aprobados.</p>
                        <form method="POST" action="{{ route('reemplazos.documentos.store', $tramite) }}" class="mt-4">
                            @csrf
                            <x-primary-button>Generar documento</x-primary-button>
                        </form>
                    @endcan
                @endif
            </section>

            @if($tramite->formalizacionReemplazo)
                @php($formalizacion = $tramite->formalizacionReemplazo)
                <section class="rounded-xl bg-white p-6 shadow">
                    <h3 class="font-semibold">Formalización registrada</h3>
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
            @elseif($tramite->estadoTramite->codigo === 'DOCUMENTO_GENERADO')
                @can('formalizar-reemplazo', $tramite)
                    <section class="rounded-xl bg-white p-6 shadow">
                        <h3 class="text-lg font-semibold">Registrar formalización</h3>
                        <div class="mt-4 rounded bg-slate-50 p-4 text-sm">
                            <p><span class="font-medium">Reemplazante:</span> {{ $tramite->reemplazo->reemplazante->nombre_completo }}</p>
                            <p><span class="font-medium">Unidad:</span> {{ $tramite->unidadOrganizacional->nombre }}</p>
                            <p><span class="font-medium">Período efectivo:</span> {{ $tramite->reemplazo->fecha_reemplazante_desde->format('d/m/Y') }} al {{ $tramite->reemplazo->fecha_reemplazante_hasta->format('d/m/Y') }}</p>
                        </div>

                        @if($estamentos->isEmpty() || $calidades->isEmpty())
                            <div class="mt-4 rounded bg-amber-50 p-4 text-sm text-amber-900">
                                @if($estamentos->isEmpty())<p>No existen estamentos activos configurados. Debe configurar el catálogo antes de formalizar este reemplazo.</p>@endif
                                @if($calidades->isEmpty())<p>No existen calidades contractuales activas configuradas. Debe configurar el catálogo antes de formalizar este reemplazo.</p>@endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('reemplazos.formalizaciones.store', $tramite) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                                @csrf
                                <fieldset class="grid gap-4 md:grid-cols-2">
                                    <legend class="mb-3 font-semibold text-slate-700">Antecedentes laborales</legend>
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
                                            @foreach($calidades as $calidad)<option value="{{ $calidad->id }}" @selected(old('calidad_contractual_id') == $calidad->id)>{{ $calidad->nombre }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm">Cargo / función *
                                        <input name="cargo_funcion" value="{{ old('cargo_funcion') }}" required maxlength="200" class="mt-1 block w-full rounded-md border-slate-300">
                                    </label>
                                    <div class="block text-sm"><span>Grado EUS</span><p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">{{ $tramite->revisionReemplazo?->grado_eus ?? 'No informado' }}</p><p class="mt-1 text-xs text-slate-500">Valor registrado durante la revisión de Gestión de Personas.</p></div>
                                </fieldset>

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
            @endif
        </div>
    </div>
</x-app-layout>
