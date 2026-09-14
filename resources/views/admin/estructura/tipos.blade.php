<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Tipos organizacionales</h2>
                <p class="mt-1 text-sm text-gray-600">Define los tipos utilizados para clasificar las unidades de la estructura organizacional.</p>
            </div>
            <button type="button" class="inline-flex shrink-0 items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" x-data x-on:click="$dispatch('open-modal', 'agregar-tipo-organizacional')">+ Agregar tipo</button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[38rem] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr class="border-b border-gray-200">
                                <th class="px-4 py-3 font-semibold sm:px-5">Nombre</th>
                                <th class="px-4 py-3 font-semibold">Código</th>
                                <th class="px-4 py-3 text-center font-semibold">Orden</th>
                                <th class="px-4 py-3 font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-5">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tipos as $tipo)
                                <tr class="transition hover:bg-gray-50/70">
                                    <td class="px-4 py-3 font-medium text-gray-900 sm:px-5">{{ $tipo->nombre }}</td>
                                    <td class="px-4 py-3"><span class="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">{{ $tipo->codigo }}</span></td>
                                    <td class="px-4 py-3 text-center text-gray-600">{{ $tipo->orden }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tipo->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $tipo->activo ? 'Activo' : 'Inactivo' }}</span></td>
                                    <td class="px-4 py-3 text-right sm:px-5">
                                        <button type="button" class="font-medium text-indigo-700 hover:underline" x-data x-on:click="$dispatch('open-modal', 'editar-tipo-{{ $tipo->id }}')">Editar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-600">No hay tipos organizacionales configurados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="{{ route('admin.estructura.index') }}" class="inline-flex text-sm font-medium text-indigo-700 hover:underline">← Volver a la estructura</a>
        </div>
    </div>

    <x-modal name="agregar-tipo-organizacional" :show="$errors->any() && old('_form_context') === 'crear'" maxWidth="xl" focusable>
        <form method="POST" action="{{ route('admin.tipos-organizacionales.store') }}" class="p-6" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
            @csrf
            <input type="hidden" name="_form_context" value="crear">
            <h3 class="text-lg font-semibold text-gray-900">Agregar tipo organizacional</h3>
            <p class="mt-1 text-sm text-gray-600">Registra un tipo para clasificar unidades dentro de la estructura.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="crear_nombre_tipo" value="Nombre" />
                    <x-text-input id="crear_nombre_tipo" name="nombre" value="{{ old('_form_context') === 'crear' ? old('nombre') : '' }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="crear_codigo_tipo" value="Código" />
                    <x-text-input id="crear_codigo_tipo" name="codigo" value="{{ old('_form_context') === 'crear' ? old('codigo') : '' }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="crear_descripcion_tipo" value="Descripción (opcional)" />
                    <textarea id="crear_descripcion_tipo" name="descripcion" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('_form_context') === 'crear' ? old('descripcion') : '' }}</textarea>
                    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="crear_orden_tipo" value="Orden" />
                    <x-text-input id="crear_orden_tipo" type="number" name="orden" value="{{ old('_form_context') === 'crear' ? old('orden', 0) : 0 }}" min="0" max="65535" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('orden')" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 self-end py-2 text-sm text-gray-700">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" @checked(old('_form_context') === 'crear' ? old('activo', true) : true) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    Activo
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                <x-primary-button x-bind:disabled="submitting">Agregar tipo</x-primary-button>
            </div>
        </form>
    </x-modal>

    @foreach ($tipos as $tipo)
        <x-modal name="editar-tipo-{{ $tipo->id }}" :show="$errors->any() && old('_form_context') === 'editar-'.$tipo->id" maxWidth="xl" focusable>
            <form method="POST" action="{{ route('admin.tipos-organizacionales.update', $tipo) }}" class="p-6" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form_context" value="editar-{{ $tipo->id }}">
                <h3 class="text-lg font-semibold text-gray-900">Editar tipo organizacional</h3>
                <p class="mt-1 text-sm text-gray-600">Actualiza sus datos, orden o disponibilidad en la estructura.</p>

                @php($esFormularioConError = old('_form_context') === 'editar-'.$tipo->id)
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="editar_nombre_tipo_{{ $tipo->id }}" value="Nombre" />
                        <x-text-input id="editar_nombre_tipo_{{ $tipo->id }}" name="nombre" value="{{ $esFormularioConError ? old('nombre') : $tipo->nombre }}" class="mt-1 block w-full" required />
                        @if ($esFormularioConError)<x-input-error :messages="$errors->get('nombre')" class="mt-2" />@endif
                    </div>
                    <div>
                        <x-input-label for="editar_codigo_tipo_{{ $tipo->id }}" value="Código" />
                        <x-text-input id="editar_codigo_tipo_{{ $tipo->id }}" name="codigo" value="{{ $esFormularioConError ? old('codigo') : $tipo->codigo }}" class="mt-1 block w-full" required />
                        @if ($esFormularioConError)<x-input-error :messages="$errors->get('codigo')" class="mt-2" />@endif
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="editar_descripcion_tipo_{{ $tipo->id }}" value="Descripción (opcional)" />
                        <textarea id="editar_descripcion_tipo_{{ $tipo->id }}" name="descripcion" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $esFormularioConError ? old('descripcion') : $tipo->descripcion }}</textarea>
                        @if ($esFormularioConError)<x-input-error :messages="$errors->get('descripcion')" class="mt-2" />@endif
                    </div>
                    <div>
                        <x-input-label for="editar_orden_tipo_{{ $tipo->id }}" value="Orden" />
                        <x-text-input id="editar_orden_tipo_{{ $tipo->id }}" type="number" name="orden" value="{{ $esFormularioConError ? old('orden') : $tipo->orden }}" min="0" max="65535" class="mt-1 block w-full" required />
                        @if ($esFormularioConError)<x-input-error :messages="$errors->get('orden')" class="mt-2" />@endif
                    </div>
                    <label class="flex items-center gap-2 self-end py-2 text-sm text-gray-700">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" @checked($esFormularioConError ? old('activo') : $tipo->activo) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Activo
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancelar</x-secondary-button>
                    <x-primary-button x-bind:disabled="submitting">Guardar cambios</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>
