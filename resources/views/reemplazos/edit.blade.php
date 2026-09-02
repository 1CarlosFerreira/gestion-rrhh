<x-app-layout>
    <div class="py-10"><div id="reemplazo-page" class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="@js([
        'unidad' => (string) old('unidad_servicio_id', $tramite->unidad_servicio_id),
        'nuevo' => (bool) old('nuevo_reemplazante_rut'),
        'seleccionado' => (bool) old('reemplazante_id', $tramite->reemplazo->reemplazante_id),
        'mismoPeriodo' => false,
        'inicioAusencia' => old('fecha_inicio_ausencia', $tramite->reemplazo->ausencia?->fecha_inicio?->format('Y-m-d')),
        'terminoAusencia' => old('fecha_termino_ausencia', $tramite->reemplazo->ausencia?->fecha_termino?->format('Y-m-d')),
        'inicioCobertura' => old('fecha_inicio', $tramite->reemplazo->fecha_inicio?->format('Y-m-d')),
        'terminoCobertura' => old('fecha_termino', $tramite->reemplazo->fecha_termino?->format('Y-m-d')),
        'confirmarEnvio' => false,
        'enviando' => false,
    ])">
        <x-flash-toast />
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
        <form id="reemplazo-form" method="POST" action="{{ route('reemplazos.update', $tramite) }}" class="contents">@csrf @method('PUT')
            <div class="sticky top-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur lg:col-span-2 print-card">
                <div>
                    <a href="{{ route('tramites.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">← Volver a Mis trámites</a>
                    <h2 class="text-xl font-semibold text-gray-900">Solicitud de Reemplazo</h2>
                    <p class="mt-1 text-sm font-medium text-gray-700">{{ $tramite->codigo }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ $tramite->estadoTramite->nombre }}</span>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <div class="flex flex-wrap justify-end gap-2 print-hidden">
                        <x-secondary-button type="submit" form="reemplazo-form" name="accion" value="guardar">{{ $tramite->estadoTramite->codigo === 'DEVUELTA_CORRECCION' ? 'Guardar cambios' : 'Guardar borrador' }}</x-secondary-button>
                        <x-primary-button type="button" x-ref="abrirEnvio" @click="confirmarEnvio = true; $nextTick(() => $refs.confirmar.focus())" x-bind:disabled="confirmarEnvio || enviando">{{ $tramite->estadoTramite->codigo === 'DEVUELTA_CORRECCION' ? 'Reenviar a Gestión de Personas' : 'Enviar a Gestión de Personas' }}</x-primary-button>
                    </div>
                    @if($sendErrors)
                        <p class="max-w-sm text-right text-xs text-amber-700">Complete los campos obligatorios{{ isset($sendErrors['documentos']) ? ' y adjunte los documentos requeridos' : '' }} antes de enviar. Guarde los cambios antes de continuar.</p>
                    @endif
                </div>
            </div>
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                <h3 class="text-lg font-semibold">1. Origen del reemplazo y justificación</h3>
                <p class="mt-1 text-sm text-slate-600">Periodo de ausencia que origina la solicitud.</p>
                <p class="text-sm">Unidad: <strong>{{ $tramite->unidadServicio->nombre }}</strong></p>
                <p class="mt-2 text-sm text-slate-600">Los campos marcados con <span aria-hidden="true">*</span> son obligatorios para enviar la solicitud.</p>
                <div class="mt-3 grid gap-3 md:grid-cols-5">
                    <label class="md:col-span-2">Tipo de reemplazo <span class="text-amber-700">*</span><select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($tiposReemplazo as $tipo)<option value="{{ $tipo->id }}" @selected($tramite->reemplazo->tipo_reemplazo_id === $tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select></label>
                    <label class="md:col-span-3">Funcionario a reemplazar<select name="funcionario_id" id="funcionario_id" class="mt-1 w-full rounded border-gray-300"><option value="">Sin funcionario asociado</option>@foreach($funcionariosUnidad as $persona) @php($vinculo=$persona->vinculos->first())<option value="{{ $persona->id }}" data-vinculo="{{ $vinculo->id }}" data-rut="{{ $persona->rut }}" @selected($tramite->reemplazo->funcionario_id === $persona->id)>{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}</option>@endforeach</select><input type="hidden" name="funcionario_vinculo_id" id="funcionario_vinculo_id" value="{{ $tramite->reemplazo->funcionario_vinculo_id }}"></label>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2"><label>Inicio de ausencia <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_inicio_ausencia" x-ref="inicioAusencia" x-model="inicioAusencia" @change="if (mismoPeriodo) inicioCobertura = inicioAusencia" class="mt-1 w-full"/></label><label>Término de ausencia <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_termino_ausencia" x-ref="terminoAusencia" x-model="terminoAusencia" @change="if (mismoPeriodo) terminoCobertura = terminoAusencia" class="mt-1 w-full"/></label></div>
                <label class="mt-5 block">Justificación <span class="text-amber-700">*</span><textarea name="justificacion" rows="4" class="mt-2 w-full rounded border-gray-300">{{ old('justificacion', $tramite->reemplazo->justificacion) }}</textarea></label>
            </section>
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                <style>[x-cloak] { display: none !important; }</style>
                <h3 class="text-lg font-semibold">2. Reemplazante propuesto</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div x-show="! nuevo && ! seleccionado" class="md:col-span-2">
                    <label>Seleccione o escriba RUT/nombre del reemplazante <span class="text-amber-700">*</span>
                        <x-text-input id="buscar_reemplazante" type="search" role="combobox" autocomplete="off" class="mt-1 w-full" :value="$tramite->reemplazo->reemplazante ? $tramite->reemplazo->reemplazante->nombre_completo.' · '.\App\Support\Rut\Rut::format($tramite->reemplazo->reemplazante->rut) : ''" placeholder="Escriba RUT, nombre o apellido"/>
                        <input type="hidden" name="reemplazante_id" id="reemplazante_id" x-ref="reemplazante" value="{{ old('reemplazante_id', $tramite->reemplazo->reemplazante_id) }}">
                        <div id="resultados_reemplazante" class="mt-1 hidden max-h-56 overflow-y-auto rounded border bg-white">
                            @foreach($personas as $persona) @continue($tramite->reemplazo->funcionario_id === $persona->id) @php($antecedente = $persona->vinculos->first())
                                <button type="button" data-persona="{{ $persona->id }}" data-rut="{{ $persona->rut }}" data-estamento="{{ $antecedente?->estamento_id }}" data-profesion="{{ $antecedente?->profesion_id }}" data-cargo="{{ $antecedente?->cargo_texto }}" data-label="{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}" class="block w-full border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-gray-50"><strong>{{ $persona->nombre_completo }}</strong> · {{ \App\Support\Rut\Rut::format($persona->rut) }}<small class="block text-gray-500">{{ $antecedente?->unidad?->nombre ?? 'Sin unidad vigente' }}</small></button>
                            @endforeach
                        </div>
                        <p id="persona_existente_mensaje" class="mt-1 text-xs text-blue-700"></p>
                        <p id="reemplazante_conflicto" class="mt-1 text-xs text-amber-700" aria-live="polite"></p>
                    </label>
                    </div>
                    <article x-show="! nuevo && seleccionado" x-cloak class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 md:col-span-2"><p id="ficha_reemplazante_nombre" class="font-semibold text-slate-900">{{ $tramite->reemplazo->reemplazante?->nombre_completo }}</p><p id="ficha_reemplazante_rut" class="mt-1 text-sm text-slate-700">{{ $tramite->reemplazo->reemplazante ? \App\Support\Rut\Rut::format($tramite->reemplazo->reemplazante->rut) : '' }}</p><div class="mt-3 flex gap-3"><button type="button" @click="seleccionado = false; $nextTick(() => document.getElementById('buscar_reemplazante').focus())" class="text-sm font-semibold text-indigo-700">Cambiar</button><button type="button" @click="seleccionado = false; $refs.reemplazante.value = ''; document.getElementById('buscar_reemplazante').value = ''" class="text-sm font-semibold text-indigo-700">Quitar</button></div></article>
                    <div class="md:col-span-2"><button type="button" @click="nuevo = ! nuevo; if (nuevo) { $refs.reemplazante.value = ''; document.getElementById('buscar_reemplazante').value = ''; document.getElementById('resultados_reemplazante').classList.add('hidden'); } else { $refs.rut.value = ''; $refs.nombres.value = ''; $refs.paterno.value = ''; $refs.materno.value = '' }" class="text-sm font-medium text-blue-700 print-hidden" x-text="nuevo ? 'Cancelar' : '+ Registrar nuevo reemplazante'">+ Registrar nuevo reemplazante</button></div>
                    <div x-show="nuevo" x-cloak class="contents">
                        <div><x-text-input id="nuevo_reemplazante_rut" x-ref="rut" name="nuevo_reemplazante_rut" :value="old('nuevo_reemplazante_rut')" placeholder="RUT"/><p id="rut_reemplazante_mensaje" class="mt-1 text-xs"></p></div>
                        <x-text-input x-ref="nombres" name="nuevo_reemplazante_nombres" :value="old('nuevo_reemplazante_nombres')" placeholder="Nombres"/>
                        <x-text-input x-ref="paterno" name="nuevo_reemplazante_apellido_paterno" :value="old('nuevo_reemplazante_apellido_paterno')" placeholder="Apellido paterno"/>
                        <x-text-input x-ref="materno" name="nuevo_reemplazante_apellido_materno" :value="old('nuevo_reemplazante_apellido_materno')" placeholder="Apellido materno (opcional)"/>
                    </div>
                </div>
            </section>
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                <h3 class="mb-3 text-lg font-semibold">3. Función y período efectivo del reemplazo</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <label>Estamento <span class="text-amber-700">*</span><select name="estamento_id" id="estamento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($estamentos as $item)<option value="{{ $item->id }}" @selected($tramite->reemplazo->estamento_id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                    <label>Profesión<select name="profesion_id" id="profesion_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($profesiones as $item)<option value="{{ $item->id }}" @selected($tramite->reemplazo->profesion_id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                    <label class="md:col-span-2">Cargo o función<x-text-input name="cargo_texto" id="cargo_texto" :value="$tramite->reemplazo->cargo_texto" class="mt-1 w-full"/></label>
                    <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="usar_mismo_periodo" value="1" x-model="mismoPeriodo" @change="mismoPeriodo = $event.target.checked; if (mismoPeriodo) { inicioCobertura = $refs.inicioAusencia.value; terminoCobertura = $refs.terminoAusencia.value }"> Usar el mismo periodo de ausencia</label>
                    <label>Fecha de inicio efectiva de cobertura <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_inicio" x-model="inicioCobertura" class="mt-1 w-full"/></label>
                    <label>Fecha de término efectiva de cobertura <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_termino" x-model="terminoCobertura" class="mt-1 w-full"/></label>
                    <p class="rounded bg-gray-50 p-3 md:col-span-2">Unidad donde desempeñará funciones: <strong>{{ $tramite->unidadServicio->nombre }}</strong></p>
                </div>
            </section>
            <x-input-error class="lg:col-span-2" :messages="$errors->all()"/>
        </form>
        <div x-show="confirmarEnvio" x-cloak @keydown.escape.window="confirmarEnvio = false; $nextTick(() => $refs.abrirEnvio.focus())" @click.self="confirmarEnvio = false; $nextTick(() => $refs.abrirEnvio.focus())" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 print-hidden" role="presentation">
            <section role="dialog" aria-modal="true" aria-labelledby="confirmar-envio-titulo" class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 id="confirmar-envio-titulo" class="text-lg font-semibold text-gray-900">Enviar solicitud a Gestión de Personas</h3>
                <p class="mt-3 text-gray-700">¿Confirmas que deseas enviar esta solicitud?</p>
                <p class="mt-2 text-sm text-gray-600">Después del envío no podrás editarla mientras se encuentre en revisión.</p>
                <dl class="mt-4 rounded bg-gray-50 p-3 text-sm text-gray-700">
                    <div><dt class="inline font-medium">Trámite:</dt> <dd class="inline">{{ $tramite->codigo }}</dd></div>
                    <div><dt class="inline font-medium">Unidad/Servicio:</dt> <dd class="inline">{{ $tramite->unidadServicio->nombre }}</dd></div>
                </dl>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="confirmarEnvio = false; $nextTick(() => $refs.abrirEnvio.focus())" x-bind:disabled="enviando" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancelar</button>
                    <button x-ref="confirmar" type="submit" form="reemplazo-send-form" x-bind:disabled="enviando" class="rounded bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60" x-text="enviando ? 'Enviando...' : 'Sí, enviar solicitud'">Sí, enviar solicitud</button>
                </div>
            </section>
        </div>
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
            <h3 class="text-lg font-semibold">4. Documentos de respaldo</h3>
            <div class="mt-4 space-y-3">
                <h4 class="font-medium">Documentos adjuntados</h4>
                @forelse($tramite->adjuntos as $adjunto)
                    <article class="rounded border p-3">
                        <p class="font-medium">{{ $adjunto->original_name }}</p>
                        <p class="text-sm text-gray-600">Tipo: {{ $adjunto->tipoDocumento?->nombre ?? 'Sin clasificación' }}</p>
                        <p class="text-sm text-gray-600">Versión: {{ $adjunto->version }} · Estado: {{ ucfirst(strtolower($adjunto->status)) }}</p>
                        <p class="text-sm text-gray-600">{{ strtoupper(pathinfo($adjunto->original_name, PATHINFO_EXTENSION)) }} · {{ number_format($adjunto->size_bytes / 1024, 1) }} KB</p>
                        <div class="mt-2 flex gap-3">
                            <a class="text-sm text-blue-700" href="{{ route('tramites.adjuntos.download', [$tramite, $adjunto]) }}">Descargar</a>
                            <details>
                                <summary class="cursor-pointer text-sm text-blue-700">Reemplazar</summary>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('tramites.adjuntos.version', [$tramite, $adjunto]) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="file" name="archivo" required>
                                    <x-secondary-button>Cargar versión</x-secondary-button>
                                </form>
                            </details>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">Aún no hay documentos adjuntos.</p>
                @endforelse
            </div>
            <h4 class="mt-5 font-medium print-hidden">Adjuntar nuevo documento</h4>
            <form method="POST" enctype="multipart/form-data" action="{{ route('tramites.adjuntos.store', $tramite) }}" class="mt-4 space-y-3 print-hidden">@csrf
                <label class="block">Archivo<input id="archivo" type="file" name="archivo" required class="mt-1 block w-full rounded border p-2"><span id="archivo_nombre" class="mt-1 block text-xs text-slate-600">Seleccione un archivo.</span></label>
                <label class="block">Tipo de documento<select id="tipo_documento_id" name="tipo_documento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione un tipo de documento</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                <p id="adjunto_ayuda" class="text-xs text-slate-600" aria-live="polite">Seleccione un archivo.</p>
                <x-primary-button id="adjuntar-documento" disabled>Adjuntar documento</x-primary-button>
            </form>
        </section>
        </div>
        <form id="reemplazo-send-form" method="POST" action="{{ route('reemplazos.send', $tramite) }}" @submit="enviando = true">@csrf</form>
    </div></div>
    <script>
        const funcionario = document.getElementById('funcionario_id');
        const reemplazante = document.getElementById('reemplazante_id');
        const resultados = document.getElementById('resultados_reemplazante');
        const buscar = document.getElementById('buscar_reemplazante');
        const nuevoRut = document.getElementById('nuevo_reemplazante_rut');
        const mensaje = document.getElementById('rut_reemplazante_mensaje');
        const personaExistenteMensaje = document.getElementById('persona_existente_mensaje');
        const reemplazanteConflicto = document.getElementById('reemplazante_conflicto');
        const archivo = document.getElementById('archivo');
        const adjuntarDocumento = document.getElementById('adjuntar-documento');
        const tipoDocumento = document.getElementById('tipo_documento_id');
        const adjuntoAyuda = document.getElementById('adjunto_ayuda');
        const archivoNombre = document.getElementById('archivo_nombre');

        function actualizarAccionAdjunto() {
            const hasFile = Boolean(archivo?.files.length);
            const hasType = Boolean(tipoDocumento?.value);
            if (archivoNombre) archivoNombre.textContent = hasFile ? archivo.files[0].name : 'Seleccione un archivo.';
            if (adjuntoAyuda) adjuntoAyuda.textContent = !hasFile ? 'Seleccione un archivo.' : (!hasType ? 'Seleccione el tipo de documento.' : 'Listo para adjuntar.');
            if (adjuntarDocumento) adjuntarDocumento.disabled = !hasFile || !hasType;
        }

        function actualizarReemplazantes() {
            const funcionarioId = funcionario?.value || '';
            const termino = buscar?.value.toLocaleLowerCase('es') || '';
            resultados?.querySelectorAll('[data-persona]').forEach((option) => {
                const ocultar = option.dataset.persona === funcionarioId || (termino && !option.textContent.toLocaleLowerCase('es').includes(termino));
                option.classList.toggle('hidden', ocultar);
            });
            if (funcionarioId && reemplazante?.value === funcionarioId) {
                reemplazante.value = '';
                buscar.value = '';
                reemplazanteConflicto.textContent = 'No puedes seleccionar como reemplazante al mismo funcionario.';
                buscar.focus();
                window.setTimeout(() => { reemplazanteConflicto.textContent = ''; }, 4000);
            }
        }

        function normalizarRut(value) {
            const limpio = value.replace(/[^0-9kK]/g, '').toUpperCase();
            return limpio.length > 1 ? `${limpio.slice(0, -1)}-${limpio.slice(-1)}` : limpio;
        }

        funcionario?.addEventListener('change', () => {
            document.getElementById('funcionario_vinculo_id').value = funcionario.selectedOptions[0]?.dataset.vinculo || '';
            actualizarReemplazantes();
        });
        buscar?.addEventListener('focus', () => {
            resultados.classList.remove('hidden');
            actualizarReemplazantes();
        });
        buscar?.addEventListener('input', () => {
            reemplazante.value = '';
            personaExistenteMensaje.textContent = '';
            resultados.classList.remove('hidden');
            actualizarReemplazantes();
        });
        resultados?.addEventListener('click', (event) => {
            const option = event.target.closest('[data-persona]');
            if (!option || option.dataset.persona === funcionario.value) return;
            reemplazante.value = option.dataset.persona;
            buscar.value = option.dataset.label;
            document.getElementById('ficha_reemplazante_nombre').textContent = option.dataset.label.split(' · ')[0];
            document.getElementById('ficha_reemplazante_rut').textContent = option.dataset.label.split(' · ')[1] || '';
            Alpine.$data(document.getElementById('reemplazo-page')).seleccionado = true;
            if (option.dataset.estamento) document.getElementById('estamento_id').value = option.dataset.estamento;
            if (option.dataset.profesion) document.getElementById('profesion_id').value = option.dataset.profesion;
            if (option.dataset.cargo) document.getElementById('cargo_texto').value = option.dataset.cargo;
            reemplazante.dispatchEvent(new Event('change'));
            resultados.classList.add('hidden');
            actualizarReemplazantes();
        });
        document.addEventListener('click', (event) => {
            if (!buscar.contains(event.target) && !resultados.contains(event.target)) resultados.classList.add('hidden');
        });
        nuevoRut?.addEventListener('input', () => {
            const rut = normalizarRut(nuevoRut.value);
            mensaje.textContent = '';
            mensaje.className = 'mt-1 text-xs';
            personaExistenteMensaje.textContent = '';
            if (rut && rut === funcionario.selectedOptions[0]?.dataset.rut) {
                mensaje.textContent = 'Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.';
                mensaje.classList.add('text-red-600');
                return;
            }
            const option = [...resultados.querySelectorAll('[data-rut]')].find((item) => item.dataset.rut === rut);
            if (!option) return;
            reemplazante.value = option.dataset.persona;
            buscar.value = option.dataset.label;
            personaExistenteMensaje.textContent = 'Esta persona ya se encuentra registrada en el sistema. Se utilizará el registro existente.';
            reemplazante.dispatchEvent(new Event('change'));
        });
        archivo?.addEventListener('change', actualizarAccionAdjunto);
        tipoDocumento?.addEventListener('change', actualizarAccionAdjunto);
        actualizarAccionAdjunto();
        actualizarReemplazantes();
    </script>
</x-app-layout>
