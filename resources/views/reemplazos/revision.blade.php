<x-app-layout>
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @if($tramite->estadoTramite->codigo === 'EN_REVISION')
                @include('reemplazos.partials.ficha-base', [
                    'volverHref' => route('gestion-personas.reemplazos.index'),
                    'volverTexto' => 'Volver a Reemplazos',
                    'fechaEtiqueta' => 'Fecha de envío',
                    'fechaValor' => $tramite->submitted_at,
                    'cabeceraCompacta' => true,
                    'mostrarContenido' => false,
                ])

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-950">Antecedentes propuestos del reemplazante</h2>
                    <p class="mt-1 text-sm text-gray-600">Información ingresada por el Solicitante. Para corregirla, devuelva la solicitud.</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs text-gray-500">Estamento</dt><dd class="mt-1 font-medium text-gray-900">{{ $tramite->reemplazo->reemplazanteEstamento?->nombre ?? 'No informado' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Profesión</dt><dd class="mt-1 font-medium text-gray-900">{{ $tramite->reemplazo->reemplazanteProfesion?->nombre ?? 'No corresponde / no informada' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Calidad contractual</dt><dd class="mt-1 font-medium text-gray-900">{{ $tramite->reemplazo->reemplazanteCalidadContractual?->nombre ?? 'No informada' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Cargo / función</dt><dd class="mt-1 font-medium text-gray-900">{{ $tramite->reemplazo->reemplazante_cargo_funcion ?? 'No informado' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-xl border border-indigo-200 bg-white p-5 shadow-md ring-1 ring-indigo-100 sm:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Tarea actual</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950">Revisión Gestión de Personas</h2>
                        <p class="mt-1 text-sm text-gray-600">Completa y valida los antecedentes administrativos para continuar la tramitación.</p>
                    </div>
                    <form id="revision-form" method="POST" action="{{ route('gestion-personas.reemplazos.save', $tramite) }}" class="mt-4 grid gap-4 lg:grid-cols-2">
                        @csrf
                        @method('PUT')

                        <label class="text-sm font-medium text-gray-700">
                            Grado E.U.S.
                            <input class="mt-1 w-full rounded border-gray-300" type="number" min="1" max="99" name="grado_eus" value="{{ old('grado_eus', $tramite->revisionReemplazo?->grado_eus) }}">
                            <x-input-error :messages="$errors->get('grado_eus')" class="mt-1" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Clasificación de área
                            <select class="mt-1 w-full rounded border-gray-300" name="clasificacion_area_id">
                                <option value="">Seleccione</option>
                                @foreach($clasificaciones as $item)
                                    <option value="{{ $item->id }}" @selected(old('clasificacion_area_id', $tramite->revisionReemplazo?->clasificacion_area_id) == $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('clasificacion_area_id')" class="mt-1" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Cumple normativa
                            <select class="mt-1 w-full rounded border-gray-300" name="cumple_normativa">
                                <option value="">Seleccione</option>
                                <option value="1" @selected((string) old('cumple_normativa', $tramite->revisionReemplazo?->cumple_normativa) === '1')>Sí</option>
                                <option value="0" @selected((string) old('cumple_normativa', $tramite->revisionReemplazo?->cumple_normativa) === '0')>No</option>
                            </select>
                            <x-input-error :messages="$errors->get('cumple_normativa')" class="mt-1" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Observación administrativa
                            <textarea class="mt-1 w-full rounded border-gray-300" name="observacion_administrativa">{{ old('observacion_administrativa', $tramite->revisionReemplazo?->observacion_administrativa) }}</textarea>
                            <x-input-error :messages="$errors->get('observacion_administrativa')" class="mt-1" />
                        </label>

                        <div class="flex flex-wrap gap-3 lg:col-span-2">
                            <x-primary-button>Guardar antecedentes</x-primary-button>
                            <x-primary-button formaction="{{ route('gestion-personas.reemplazos.approve', $tramite) }}">Aprobar antecedentes</x-primary-button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-semibold text-gray-950">Devolver para corrección</h2>
                    <p class="mt-1 text-sm text-gray-600">Indica al solicitante qué debe corregir antes de continuar.</p>
                    <form method="POST" action="{{ route('gestion-personas.reemplazos.return', $tramite) }}" class="mt-5 flex flex-col gap-2 border-t border-gray-200 pt-5 sm:flex-row">
                        @csrf
                        <input required maxlength="5000" name="observation" class="w-full rounded border-gray-300" placeholder="Observación obligatoria para devolver">
                        <x-secondary-button>Devolver</x-secondary-button>
                    </form>
                </section>

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-semibold text-gray-900 transition hover:bg-gray-50 sm:px-6">
                        <span>Ver antecedentes de la solicitud</span>
                        <svg class="h-5 w-5 shrink-0 text-gray-500 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </summary>
                    <div class="space-y-5 border-t border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                        @include('reemplazos.partials.ficha-base', [
                            'mostrarCabecera' => false,
                        ])
                        @include('reemplazos.partials.adjuntos')
                    </div>
                </details>
            @else
                @include('reemplazos.partials.ficha-base', [
                    'volverHref' => route('gestion-personas.reemplazos.index'),
                    'volverTexto' => 'Volver a Reemplazos',
                    'fechaEtiqueta' => 'Fecha de envío',
                    'fechaValor' => $tramite->submitted_at,
                    'mostrarIniciarRevision' => $tramite->estadoTramite->codigo === 'ENVIADA_GESTION_PERSONAS',
                ])

                @include('reemplazos.partials.adjuntos')
            @endif
        </div>
    </div>
</x-app-layout>
