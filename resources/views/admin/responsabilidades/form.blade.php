@php
    $esEdicion = $responsabilidad !== null;
    $tipoSeleccionado = old('tipo', $responsabilidad?->tipo?->value ?? $tipos[0]->value);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">{{ $esEdicion ? 'Editar responsabilidad institucional' : 'Registrar responsabilidad institucional' }}</h2>
            <p class="mt-1 text-sm text-gray-600">Asigna una persona como responsable de una unidad organizacional durante un periodo determinado.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <form method="POST" action="{{ $esEdicion ? route('admin.responsabilidades.update', $responsabilidad) : route('admin.responsabilidades.store') }}" class="mx-auto max-w-3xl space-y-6 rounded-xl bg-white p-5 shadow-sm sm:p-6">
            @csrf
            @if ($esEdicion)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="unidad_organizacional_id" value="Unidad" />
                <select id="unidad_organizacional_id" name="unidad_organizacional_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Seleccione una unidad</option>
                    @foreach ($unidades as $unidad)
                        <option value="{{ $unidad['id'] }}" @selected(old('unidad_organizacional_id', $responsabilidad?->unidad_organizacional_id) == $unidad['id'])>{{ last(explode(' / ', $unidad['ruta'])) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unidad_organizacional_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="persona_id" value="Persona" />
                <p class="mt-1 text-xs text-gray-500">Selecciona por nombre y confirma su identidad mediante el RUT.</p>
                <select id="persona_id" name="persona_id" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Seleccione una persona</option>
                    @foreach ($personas as $persona)
                        <option value="{{ $persona->id }}" @selected(old('persona_id', $responsabilidad?->persona_id) == $persona->id)>{{ $persona->nombre_completo }} · RUT {{ $persona->rut }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('persona_id')" class="mt-2" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">Tipo</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($tipos as $tipo)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/40 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                            <input type="radio" name="tipo" value="{{ $tipo->value }}" @checked($tipoSeleccionado === $tipo->value) class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-gray-900">{{ $tipo->etiqueta() }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="vigente_desde" value="Vigente desde" />
                    <x-text-input id="vigente_desde" type="date" name="vigente_desde" value="{{ old('vigente_desde', $responsabilidad?->vigente_desde?->toDateString()) }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('vigente_desde')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="vigente_hasta" value="Vigente hasta (opcional)" />
                    <x-text-input id="vigente_hasta" type="date" name="vigente_hasta" value="{{ old('vigente_hasta', $responsabilidad?->vigente_hasta?->toDateString()) }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('vigente_hasta')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="puede_aprobar" value="1" @checked(old('puede_aprobar', $responsabilidad?->puede_aprobar)) class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">Puede aprobar trámites de esta unidad</span>
                        <span class="mt-1 block text-sm leading-5 text-gray-600">Habilita a esta persona como responsable con capacidad de aprobación cuando el trámite lo requiera.</span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('puede_aprobar')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="observacion" value="Observación (opcional)" />
                <textarea id="observacion" name="observacion" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Agrega contexto relevante para esta responsabilidad">{{ old('observacion', $responsabilidad?->observacion) }}</textarea>
                <x-input-error :messages="$errors->get('observacion')" class="mt-2" />
            </div>

            <x-input-error :messages="$errors->get('responsabilidad')" />

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.responsabilidades.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Cancelar</a>
                <x-primary-button class="justify-center">{{ $esEdicion ? 'Guardar cambios' : 'Registrar responsabilidad' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
