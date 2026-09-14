<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Calidades contractuales</h2>
                <p class="mt-1 text-sm text-gray-600">Administra las modalidades contractuales utilizadas en la dotación.</p>
            </div>

            @can('calidades_contractuales.gestionar')
                <button type="button" class="inline-flex shrink-0 items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" x-data x-on:click="$dispatch('open-modal', 'crear-calidad-contractual')">+ Agregar calidad contractual</button>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.calidades.index') }}" class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:p-5">
                <x-text-input name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por código o nombre" class="min-w-0 flex-1" />
                <x-secondary-button type="submit" class="justify-center">Buscar</x-secondary-button>
                @if (request()->filled('buscar'))
                    <a href="{{ route('admin.calidades.index') }}" class="text-center text-sm font-medium text-indigo-700 hover:underline">Limpiar</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr class="border-b border-gray-200">
                                <th class="px-4 py-3 font-semibold sm:px-5">Nombre</th>
                                <th class="px-4 py-3 font-semibold">Código</th>
                                <th class="px-4 py-3 text-center font-semibold">Vínculos</th>
                                <th class="px-4 py-3 font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-5">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($calidades as $calidad)
                                <tr class="transition hover:bg-gray-50/70">
                                    <td class="px-4 py-3 font-medium text-gray-900 sm:px-5">{{ $calidad->nombre }}</td>
                                    <td class="px-4 py-3"><span class="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">{{ $calidad->codigo }}</span></td>
                                    <td class="px-4 py-3 text-center text-gray-600">{{ $calidad->vinculos_count }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $calidad->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $calidad->activo ? 'Activa' : 'Inactiva' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right sm:px-5">
                                        @can('calidades_contractuales.gestionar')
                                            <button type="button" class="font-medium text-indigo-700 hover:underline" x-data x-on:click="$dispatch('open-modal', 'editar-calidad-{{ $calidad->id }}')">Editar</button>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-600">No hay calidades contractuales configuradas. Ingrese el catálogo institucional validado antes de registrar dotación.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @can('calidades_contractuales.gestionar')
        <x-modal name="crear-calidad-contractual" :show="$errors->any() && old('_form_context') === 'crear'" maxWidth="xl" focusable>
            <form method="POST" action="{{ route('admin.calidades.store') }}" class="p-6" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                @csrf
                <input type="hidden" name="_form_context" value="crear">
                <h3 class="text-lg font-semibold text-gray-900">Agregar calidad contractual</h3>
                <p class="mt-1 text-sm text-gray-600">Registra una modalidad contractual para utilizarla en la dotación.</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="crear_nombre" value="Nombre" />
                        <x-text-input id="crear_nombre" name="nombre" value="{{ old('_form_context') === 'crear' ? old('nombre') : '' }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="crear_codigo" value="Código" />
                        <x-text-input id="crear_codigo" name="codigo" value="{{ old('_form_context') === 'crear' ? old('codigo') : '' }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="crear_descripcion" value="Descripción (opcional)" />
                        <textarea id="crear_descripcion" name="descripcion" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('_form_context') === 'crear' ? old('descripcion') : '' }}</textarea>
                        <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="crear_orden" value="Orden" />
                        <x-text-input id="crear_orden" type="number" name="orden" value="{{ old('_form_context') === 'crear' ? old('orden', 0) : 0 }}" min="0" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('orden')" class="mt-2" />
                    </div>
                    <label class="flex items-center gap-2 self-end py-2 text-sm text-gray-700">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" @checked(old('_form_context') === 'crear' ? old('activo', true) : true) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Activa
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                    <x-primary-button x-bind:disabled="submitting">Agregar calidad contractual</x-primary-button>
                </div>
            </form>
        </x-modal>

        @foreach ($calidades as $calidad)
            <x-modal name="editar-calidad-{{ $calidad->id }}" :show="$errors->any() && old('_form_context') === 'editar-'.$calidad->id" maxWidth="xl" focusable>
                <form method="POST" action="{{ route('admin.calidades.update', $calidad) }}" class="p-6" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form_context" value="editar-{{ $calidad->id }}">
                    <h3 class="text-lg font-semibold text-gray-900">Editar calidad contractual</h3>
                    <p class="mt-1 text-sm text-gray-600">Actualiza sus datos, orden o disponibilidad en la dotación.</p>

                    @php($esFormularioConError = old('_form_context') === 'editar-'.$calidad->id)
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="editar_nombre_{{ $calidad->id }}" value="Nombre" />
                            <x-text-input id="editar_nombre_{{ $calidad->id }}" name="nombre" value="{{ $esFormularioConError ? old('nombre') : $calidad->nombre }}" class="mt-1 block w-full" required />
                            @if ($esFormularioConError)<x-input-error :messages="$errors->get('nombre')" class="mt-2" />@endif
                        </div>
                        <div>
                            <x-input-label for="editar_codigo_{{ $calidad->id }}" value="Código" />
                            <x-text-input id="editar_codigo_{{ $calidad->id }}" name="codigo" value="{{ $esFormularioConError ? old('codigo') : $calidad->codigo }}" class="mt-1 block w-full" required />
                            @if ($esFormularioConError)<x-input-error :messages="$errors->get('codigo')" class="mt-2" />@endif
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="editar_descripcion_{{ $calidad->id }}" value="Descripción (opcional)" />
                            <textarea id="editar_descripcion_{{ $calidad->id }}" name="descripcion" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $esFormularioConError ? old('descripcion') : $calidad->descripcion }}</textarea>
                            @if ($esFormularioConError)<x-input-error :messages="$errors->get('descripcion')" class="mt-2" />@endif
                        </div>
                        <div>
                            <x-input-label for="editar_orden_{{ $calidad->id }}" value="Orden" />
                            <x-text-input id="editar_orden_{{ $calidad->id }}" type="number" name="orden" value="{{ $esFormularioConError ? old('orden') : $calidad->orden }}" min="0" class="mt-1 block w-full" required />
                            @if ($esFormularioConError)<x-input-error :messages="$errors->get('orden')" class="mt-2" />@endif
                        </div>
                        <label class="flex items-center gap-2 self-end py-2 text-sm text-gray-700">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" value="1" @checked($esFormularioConError ? old('activo') : $calidad->activo) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Activa
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                        <x-primary-button x-bind:disabled="submitting">Guardar cambios</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endcan
</x-app-layout>
