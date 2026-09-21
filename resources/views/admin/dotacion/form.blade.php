@php
    $personaDeterminadaId = old('persona_id', $personaSeleccionadaId ?? null);
    $personaDeterminada = ! $vinculo && $personaDeterminadaId
        ? $personas->firstWhere('id', (int) $personaDeterminadaId)
        : null;
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">{{ $vinculo ? 'Editar vínculo laboral' : 'Registrar vínculo laboral' }}</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6">
            @if (! $vinculo)
                <x-admin-process-steps current="dotacion" />
            @endif

            @if ($calidades->isEmpty())
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Debe <a class="font-medium underline" href="{{ route('admin.calidades.index') }}">configurar calidades contractuales</a> antes de registrar dotación.</div>
            @else
                <form method="POST" action="{{ $vinculo ? route('admin.dotacion.update', $vinculo) : route('admin.dotacion.store') }}" class="space-y-5" @if (! $vinculo) x-data="{ responsabilidadTipo: @js(old('responsabilidad_tipo', 'FUNCIONARIO')), vinculoDesde: @js(old('vigente_desde')), responsabilidadDesde: @js(old('responsabilidad_desde')), responsabilidadDesdeModificada: @js(old('responsabilidad_desde_editada', '0') === '1') }" @endif>
                    @csrf
                    @if ($vinculo) @method('PUT') @endif
                    @if (request('return_to') === 'persona')
                        <input type="hidden" name="return_to" value="persona">
                    @endif

                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                            <p class="font-semibold">No fue posible guardar el vínculo. Revisa los campos indicados.</p>
                            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            @if ($errors->has('responsabilidad_incompatible') && $vinculo)
                                <a href="{{ route('admin.responsabilidades.index', ['persona' => $vinculo->persona->rut]) }}" class="mt-3 inline-flex font-medium text-red-800 underline">Administrar responsabilidad afectada</a>
                            @endif
                        </div>
                    @endif

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-base font-semibold text-gray-900">Persona</h2>
                        @if ($personaDeterminada)
                            <input type="hidden" name="persona_id" value="{{ $personaDeterminada->id }}">
                            <div class="mt-3 rounded-lg border border-indigo-100 bg-indigo-50/50 px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $personaDeterminada->nombre_completo }}</p>
                                <p class="mt-1 text-sm text-gray-600">RUT {{ $personaDeterminada->rut }}</p>
                            </div>
                            <x-input-error :messages="$errors->get('persona_id')" class="mt-2" />
                        @else
                            <div class="mt-4">
                                <x-input-label for="persona_id" value="Persona" />
                                <select id="persona_id" name="persona_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    @foreach($personas as $persona)
                                        <option value="{{ $persona->id }}" @selected(old('persona_id', $vinculo?->persona_id) == $persona->id)>{{ $persona->nombre_completo }} · {{ $persona->rut }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('persona_id')" class="mt-2" />
                            </div>
                        @endif
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-base font-semibold text-gray-900">Antecedentes laborales</h2>
                        <p class="mt-1 text-sm text-gray-500">Información del vínculo de la persona con la unidad.</p>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="unidad_organizacional_id" value="Unidad" />
                                <select id="unidad_organizacional_id" name="unidad_organizacional_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>@foreach($unidades as $unidad)<option value="{{ $unidad['id'] }}" @selected(old('unidad_organizacional_id', $vinculo?->unidad_organizacional_id) == $unidad['id'])>{{ Illuminate\Support\Str::afterLast($unidad['ruta'], ' / ') }}</option>@endforeach</select>
                                <x-input-error :messages="$errors->get('unidad_organizacional_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="estamento_id" value="Estamento" />
                                <select id="estamento_id" name="estamento_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>@foreach($estamentos as $estamento)<option value="{{ $estamento->id }}" @selected(old('estamento_id', $vinculo?->estamento_id) == $estamento->id)>{{ $estamento->nombre }}</option>@endforeach</select>
                                <x-input-error :messages="$errors->get('estamento_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="profesion_id" value="Profesión" />
                                <select id="profesion_id" name="profesion_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Sin profesión</option>@foreach($profesiones as $profesion)<option value="{{ $profesion->id }}" @selected(old('profesion_id', $vinculo?->profesion_id) == $profesion->id)>{{ $profesion->nombre }}</option>@endforeach</select>
                                <x-input-error :messages="$errors->get('profesion_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="calidad_contractual_id" value="Calidad contractual" />
                                <select id="calidad_contractual_id" name="calidad_contractual_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>@foreach($calidades as $calidad)<option value="{{ $calidad->id }}" @selected(old('calidad_contractual_id', $vinculo?->calidad_contractual_id) == $calidad->id)>{{ $calidad->nombre }}</option>@endforeach</select>
                                <x-input-error :messages="$errors->get('calidad_contractual_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cargo_funcion" value="Cargo o función" />
                                <x-text-input id="cargo_funcion" class="mt-1 block w-full" name="cargo_funcion" value="{{ old('cargo_funcion', $vinculo?->cargo_funcion) }}" required />
                                <x-input-error :messages="$errors->get('cargo_funcion')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="grado_eus" value="Grado E.U.S." />
                                <x-text-input id="grado_eus" type="number" min="1" max="99" class="mt-1 block w-full" name="grado_eus" value="{{ old('grado_eus', $vinculo?->grado_eus) }}" />
                                <x-input-error :messages="$errors->get('grado_eus')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="vigente_desde" value="Desde" />
                                <input id="vigente_desde" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" name="vigente_desde" value="{{ old('vigente_desde', $vinculo?->vigente_desde?->toDateString()) }}" @if (! $vinculo) x-model="vinculoDesde" x-on:input="if (responsabilidadTipo !== 'FUNCIONARIO' && ! responsabilidadDesdeModificada) responsabilidadDesde = vinculoDesde" @endif required>
                                <x-input-error :messages="$errors->get('vigente_desde')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="vigente_hasta" value="Hasta (opcional)" />
                                <x-text-input id="vigente_hasta" type="date" class="mt-1 block w-full" name="vigente_hasta" value="{{ old('vigente_hasta', $vinculo?->vigente_hasta?->toDateString()) }}" />
                                <x-input-error :messages="$errors->get('vigente_hasta')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="observacion" value="Observación" />
                                <textarea id="observacion" name="observacion" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('observacion', $vinculo?->observacion) }}</textarea>
                                <x-input-error :messages="$errors->get('observacion')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-base font-semibold text-gray-900">Origen</h2>
                        <div class="mt-4 max-w-sm">
                            <x-input-label for="origen" value="Origen del vínculo" />
                            <select id="origen" name="origen" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>@foreach($origenes as $origen)<option value="{{ $origen->value }}" @selected(old('origen', $vinculo?->origen?->value ?? 'MANUAL') === $origen->value)>{{ $origen->etiqueta() }}</option>@endforeach</select>
                            <x-input-error :messages="$errors->get('origen')" class="mt-2" />
                        </div>
                    </section>

                    @if (! $vinculo)
                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                            <fieldset>
                                <legend class="text-base font-semibold text-gray-900">Responsabilidad en la unidad</legend>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    @foreach(['FUNCIONARIO' => 'Funcionario', 'TITULAR' => 'Titular', 'SUBROGANTE' => 'Subrogante'] as $valor => $etiqueta)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                                            <input type="radio" name="responsabilidad_tipo" value="{{ $valor }}" @checked(old('responsabilidad_tipo', 'FUNCIONARIO') === $valor) x-model="responsabilidadTipo" x-on:change="if (responsabilidadTipo !== 'FUNCIONARIO' && ! responsabilidadDesdeModificada) responsabilidadDesde = vinculoDesde" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="font-medium text-gray-800">{{ $etiqueta }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('responsabilidad_tipo')" class="mt-2" />
                            </fieldset>

                            <input type="hidden" name="responsabilidad_desde_editada" x-bind:value="responsabilidadDesdeModificada ? '1' : '0'">
                            <div x-cloak x-show="responsabilidadTipo !== 'FUNCIONARIO'" class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50/40 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Responsabilidad institucional</h3>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="responsabilidad_desde" value="Desde" />
                                        <x-text-input id="responsabilidad_desde" type="date" class="mt-1 block w-full" name="responsabilidad_desde" x-model="responsabilidadDesde" x-on:input="responsabilidadDesdeModificada = true" x-bind:required="responsabilidadTipo !== 'FUNCIONARIO'" />
                                        <x-input-error :messages="$errors->get('responsabilidad_desde')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="responsabilidad_hasta" value="Hasta (opcional)" />
                                        <x-text-input id="responsabilidad_hasta" type="date" class="mt-1 block w-full" name="responsabilidad_hasta" value="{{ old('responsabilidad_hasta') }}" />
                                        <x-input-error :messages="$errors->get('responsabilidad_hasta')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ request('return_to') === 'persona' && $vinculo ? route('admin.personas.show', $vinculo->persona_id) : ($personaDeterminada ? route('admin.personas.show', $personaDeterminada) : route('admin.dotacion.index')) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">{{ $vinculo ? 'Volver' : 'Cancelar' }}</a>
                        <x-primary-button class="justify-center">{{ $vinculo ? 'Guardar cambios' : 'Guardar vínculo y continuar →' }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
