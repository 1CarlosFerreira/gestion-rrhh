@php
    $esEdicion = $acceso !== null;
    $alcanceSeleccionado = old('alcance', $acceso?->alcance?->value ?? $alcances[0]->value);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">{{ $esEdicion ? 'Editar acceso por unidad' : 'Asignar acceso por unidad' }}</h2>
            <p class="mt-1 text-sm text-gray-600">Define en qué unidad puede operar el usuario y hasta dónde se extiende su acceso.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <form method="POST" action="{{ $esEdicion ? route('admin.accesos.update', $acceso) : route('admin.accesos.store') }}" class="mx-auto max-w-3xl space-y-6 rounded-xl bg-white p-5 shadow-sm sm:p-6">
            @csrf
            @if ($esEdicion)
                @method('PUT')
            @endif

            <div>
                <x-input-label for="user_id" value="Usuario" />
                <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Seleccione un usuario</option>
                    @foreach ($users as $usuario)
                        <option value="{{ $usuario->id }}" @selected(old('user_id', $acceso?->user_id) == $usuario->id)>{{ $usuario->name }} · {{ $usuario->persona?->rut }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="unidad_organizacional_id" value="Unidad" />
                <select id="unidad_organizacional_id" name="unidad_organizacional_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($unidades as $unidad)
                        <option value="{{ $unidad['id'] }}" @selected(old('unidad_organizacional_id', $acceso?->unidad_organizacional_id) == $unidad['id'])>{{ last(explode(' / ', $unidad['ruta'])) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unidad_organizacional_id')" class="mt-2" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">Alcance</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($alcances as $alcance)
                        @php($esSoloUnidad = $alcance->value === 'SOLO_UNIDAD')
                        <label class="relative flex cursor-pointer gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/40 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                            <input type="radio" name="alcance" value="{{ $alcance->value }}" @checked($alcanceSeleccionado === $alcance->value) class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $esSoloUnidad ? 'Solo esta unidad' : 'Unidad y descendientes' }}</span>
                                <span class="mt-1 block text-sm leading-5 text-gray-600">{{ $esSoloUnidad ? 'Podrá operar únicamente en la unidad seleccionada.' : 'Podrá operar en esta unidad y en todas las unidades que dependan de ella.' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('alcance')" class="mt-2" />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="vigente_desde" value="Desde" />
                    <x-text-input id="vigente_desde" type="date" name="vigente_desde" value="{{ old('vigente_desde', $acceso?->vigente_desde?->toDateString()) }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('vigente_desde')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="vigente_hasta" value="Hasta (opcional)" />
                    <x-text-input id="vigente_hasta" type="date" name="vigente_hasta" value="{{ old('vigente_hasta', $acceso?->vigente_hasta?->toDateString()) }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('vigente_hasta')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="observacion" value="Observación (opcional)" />
                <textarea id="observacion" name="observacion" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Agrega contexto relevante para esta asignación">{{ old('observacion', $acceso?->observacion) }}</textarea>
                <x-input-error :messages="$errors->get('observacion')" class="mt-2" />
            </div>

            <x-input-error :messages="$errors->get('acceso')" />

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.accesos.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Cancelar</a>
                <x-primary-button class="justify-center">{{ $esEdicion ? 'Guardar cambios' : 'Asignar acceso' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
