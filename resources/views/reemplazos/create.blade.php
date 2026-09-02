<x-app-layout>
    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash-toast />
            <form method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.store') }}" class="space-y-6" x-data="@js([
                'unidad' => (string) old('unidad_servicio_id', $unidadInicial?->id),
                'nuevo' => (bool) old('nuevo_reemplazante_rut'),
                'seleccionado' => (bool) old('reemplazante_id'),
                'mismoPeriodo' => (bool) old('usar_mismo_periodo'),
                'inicioAusencia' => old('fecha_inicio_ausencia', ''),
                'terminoAusencia' => old('fecha_termino_ausencia', ''),
                'inicioCobertura' => old('fecha_inicio', ''),
                'terminoCobertura' => old('fecha_termino', ''),
                ])">
                @csrf

                <div class="sticky top-20 z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur print-card">
                    <div>
                        <a href="{{ route('tramites.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">← Volver a Mis trámites</a>
                        <h2 class="text-xl font-semibold text-gray-900">Solicitud de Reemplazo</h2>
                        <p class="mt-1 text-sm text-gray-600">Complete los antecedentes de la solicitud. Puede guardar el borrador y continuar más tarde.</p>
                        <span class="mt-2 inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">Estado: Nueva solicitud</span>
                    </div>
                    <x-primary-button name="accion" value="guardar" class="print-hidden" :disabled="$unidades->isEmpty()">Guardar borrador</x-primary-button>
                </div>

                @if($unidades->isEmpty())
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900"><h3 class="font-semibold">No tienes una unidad vigente asignada.</h3><p class="mt-1 text-sm">Solicita apoyo al administrador para crear una solicitud.</p></section>
                @else
                <p class="text-sm text-slate-600 lg:col-span-2">Los campos marcados con <span aria-hidden="true">*</span> son obligatorios para enviar la solicitud.</p>
                <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                    <h3 class="text-lg font-semibold text-gray-900">1. Origen del reemplazo y justificación</h3>
                    <p class="mt-1 text-sm text-slate-600">Periodo de ausencia que origina la solicitud.</p>
                    <div class="mt-3 grid gap-4 md:grid-cols-5">
                        <label class="md:col-span-2">Unidad/Servicio
                            @if($unidadPreseleccionada)<input type="hidden" name="unidad_servicio_id" value="{{ $unidadPreseleccionada->id }}" data-label="{{ $unidadPreseleccionada->nombre }}"><p class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">{{ $unidadPreseleccionada->nombre }}</p>@else
                            <select name="unidad_servicio_id" x-model="unidad" class="mt-1 w-full rounded border-gray-300">
                                <option value="">Seleccione</option>
                                @foreach($unidades as $unidad)
                                    <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                            @endif
                        </label>
                        <label class="md:col-span-2">Tipo de reemplazo <span class="text-amber-700">*</span>
                            <select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300">
                                <option value="">Seleccione</option>
                                @foreach($tiposReemplazo as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((int) old('tipo_reemplazo_id') === $tipo->id)>{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="md:col-span-3">Funcionario a reemplazar
                            <select name="funcionario_id" id="funcionario_id" class="mt-1 w-full rounded border-gray-300" :disabled="!unidad">
                                <option value="" x-text="unidad ? 'Sin funcionario asociado' : 'Seleccione primero una unidad'">Seleccione primero una unidad</option>
                                @foreach($funcionariosUnidad as $persona)
                                    @foreach($persona->vinculos as $vinculo)
                                        <option value="{{ $persona->id }}" data-unidad="{{ $vinculo->unidad_servicio_id }}" data-vinculo="{{ $vinculo->id }}" data-rut="{{ $persona->rut }}" x-show="unidad === '{{ $vinculo->unidad_servicio_id }}'" @selected((int) old('funcionario_id', $funcionarioPreseleccionado) === $persona->id)>{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <input type="hidden" name="funcionario_vinculo_id" id="funcionario_vinculo_id" value="{{ old('funcionario_vinculo_id') }}">
                            <p class="mt-1 text-xs text-gray-500">Se muestra únicamente la dotación activa y vigente de la unidad seleccionada.</p>
                        </label>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2"><label>Inicio de ausencia <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_inicio_ausencia" x-ref="inicioAusencia" x-model="inicioAusencia" @change="if (mismoPeriodo) inicioCobertura = inicioAusencia" class="mt-1 w-full"/></label><label>Término de ausencia <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_termino_ausencia" x-ref="terminoAusencia" x-model="terminoAusencia" @change="if (mismoPeriodo) terminoCobertura = terminoAusencia" class="mt-1 w-full"/></label></div>
                    <label class="mt-5 block">Justificación <span class="text-amber-700">*</span>
                        <textarea name="justificacion" rows="4" class="mt-2 w-full rounded border-gray-300">{{ old('justificacion') }}</textarea>
                    </label>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                    <style>[x-cloak] { display: none !important; }</style>
                    <h3 class="text-lg font-semibold text-gray-900">2. Reemplazante propuesto</h3>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div x-show="! nuevo && ! seleccionado" class="md:col-span-2">
                        <label>Seleccione o escriba RUT/nombre del reemplazante <span class="text-amber-700">*</span>
                            <x-text-input id="buscar_reemplazante" type="search" role="combobox" autocomplete="off" class="mt-1 w-full" placeholder="Escriba RUT, nombre o apellido"/>
                            <input type="hidden" name="reemplazante_id" id="reemplazante_id" x-ref="reemplazante" value="{{ old('reemplazante_id') }}" @change="if ($event.target.value) { nuevo = false; $refs.rut.value = ''; $refs.nombres.value = ''; $refs.paterno.value = ''; $refs.materno.value = '' }">
                            <div id="resultados_reemplazante" class="mt-1 hidden max-h-56 overflow-y-auto rounded border bg-white">
                                @foreach($personas as $persona) @php($antecedente = $persona->vinculos->first())
                                    <button type="button" data-persona="{{ $persona->id }}" data-rut="{{ $persona->rut }}" data-estamento="{{ $antecedente?->estamento_id }}" data-profesion="{{ $antecedente?->profesion_id }}" data-cargo="{{ $antecedente?->cargo_texto }}" data-label="{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}" class="block w-full border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-gray-50"><strong>{{ $persona->nombre_completo }}</strong> · {{ \App\Support\Rut\Rut::format($persona->rut) }}<small class="block text-gray-500">{{ $antecedente?->unidad?->nombre ?? 'Sin unidad vigente' }}</small></button>
                                @endforeach
                            </div>
                            <p id="persona_existente_mensaje" class="mt-1 text-xs text-blue-700"></p>
                            <p id="reemplazante_conflicto" class="mt-1 text-xs text-amber-700" aria-live="polite"></p>
                        </label>
                        </div>
                        <article x-show="! nuevo && seleccionado" x-cloak class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 md:col-span-2"><p id="ficha_reemplazante_nombre" class="font-semibold text-slate-900"></p><p id="ficha_reemplazante_rut" class="mt-1 text-sm text-slate-700"></p><div class="mt-3 flex gap-3"><button type="button" @click="seleccionado = false; $nextTick(() => document.getElementById('buscar_reemplazante').focus())" class="text-sm font-semibold text-indigo-700">Cambiar</button><button type="button" @click="seleccionado = false; $refs.reemplazante.value = ''; document.getElementById('buscar_reemplazante').value = ''" class="text-sm font-semibold text-indigo-700">Quitar</button></div></article>
                        <div class="md:col-span-2"><button type="button" @click="nuevo = ! nuevo; if (nuevo) { $refs.reemplazante.value = ''; document.getElementById('buscar_reemplazante').value = ''; document.getElementById('resultados_reemplazante').classList.add('hidden'); } else { $refs.rut.value = ''; $refs.nombres.value = ''; $refs.paterno.value = ''; $refs.materno.value = '' }" class="text-sm font-medium text-blue-700" x-text="nuevo ? 'Cancelar' : '+ Registrar nuevo reemplazante'">+ Registrar nuevo reemplazante</button></div>
                        <div x-show="nuevo" x-cloak class="contents">
                            <div><x-text-input id="nuevo_reemplazante_rut" x-ref="rut" name="nuevo_reemplazante_rut" :value="old('nuevo_reemplazante_rut')" placeholder="RUT"/><p id="rut_reemplazante_mensaje" class="mt-1 text-xs"></p></div>
                            <x-text-input x-ref="nombres" name="nuevo_reemplazante_nombres" :value="old('nuevo_reemplazante_nombres')" placeholder="Nombres"/>
                            <x-text-input x-ref="paterno" name="nuevo_reemplazante_apellido_paterno" :value="old('nuevo_reemplazante_apellido_paterno')" placeholder="Apellido paterno"/>
                            <x-text-input x-ref="materno" name="nuevo_reemplazante_apellido_materno" :value="old('nuevo_reemplazante_apellido_materno')" placeholder="Apellido materno (opcional)"/>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                    <h3 class="text-lg font-semibold text-gray-900">3. Función y período efectivo del reemplazo</h3>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <label>Estamento <span class="text-amber-700">*</span><select name="estamento_id" id="estamento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($estamentos as $item)<option value="{{ $item->id }}" @selected((int) old('estamento_id') === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                        <label>Profesión<select name="profesion_id" id="profesion_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($profesiones as $item)<option value="{{ $item->id }}" @selected((int) old('profesion_id') === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                        <label>Cargo o función<x-text-input name="cargo_texto" id="cargo_texto" :value="old('cargo_texto')" class="mt-1 w-full"/></label><span></span>
                        <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="usar_mismo_periodo" value="1" x-model="mismoPeriodo" @change="mismoPeriodo = $event.target.checked; if (mismoPeriodo) { inicioCobertura = $refs.inicioAusencia.value; terminoCobertura = $refs.terminoAusencia.value }"> Usar el mismo periodo de ausencia</label>
                        <label>Fecha de inicio efectiva de cobertura <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_inicio" x-model="inicioCobertura" class="mt-1 w-full"/></label>
                        <label>Fecha de término efectiva de cobertura <span class="text-amber-700">*</span><x-text-input type="date" name="fecha_termino" x-model="terminoCobertura" class="mt-1 w-full"/></label>
                        <p class="rounded bg-gray-50 p-3 md:col-span-2">Unidad destino: <strong id="unidad_destino">Se completará al seleccionar la unidad</strong></p>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm print-card">
                    <h3 class="text-lg font-semibold text-gray-900">4. Documentos de respaldo</h3>
                    <p class="mt-2 text-sm text-gray-600">Guarda primero el borrador para poder adjuntar documentos.</p>
                </section>
                </div>

                <x-input-error :messages="$errors->all()"/>
                @endif
            </form>
        </div>
    </div>

    <script>
        const unidad = document.querySelector('[name="unidad_servicio_id"]');
        const funcionario = document.getElementById('funcionario_id');
        const vinculo = document.getElementById('funcionario_vinculo_id');
        const destino = document.getElementById('unidad_destino');
        const buscarReemplazante = document.getElementById('buscar_reemplazante');
        const reemplazante = document.getElementById('reemplazante_id');
        const resultadosReemplazante = document.getElementById('resultados_reemplazante');
        const nuevoRut = document.getElementById('nuevo_reemplazante_rut');
        const rutMensaje = document.getElementById('rut_reemplazante_mensaje');
        const personaExistenteMensaje = document.getElementById('persona_existente_mensaje');
        const reemplazanteConflicto = document.getElementById('reemplazante_conflicto');

        function actualizarUnidad() {
            const unidadId = unidad?.value || '';
            funcionario?.querySelectorAll('option[data-unidad]').forEach((option) => {
                option.hidden = option.dataset.unidad !== unidadId;
                option.disabled = option.dataset.unidad !== unidadId;
            });
            if (funcionario?.selectedOptions[0]?.dataset.unidad !== unidadId) {
                funcionario.value = '';
                vinculo.value = '';
            }
            destino.textContent = unidadId ? (unidad.selectedOptions?.[0]?.text || unidad.dataset.label) : 'Se completará al seleccionar la unidad';
            actualizarReemplazantes();
        }

        function actualizarReemplazantes() {
            const funcionarioId = funcionario?.value || '';
            const termino = buscarReemplazante?.value.toLocaleLowerCase('es') || '';
            resultadosReemplazante?.querySelectorAll('[data-persona]').forEach((option) => {
                const ocultar = option.dataset.persona === funcionarioId || (termino !== '' && !option.textContent.toLocaleLowerCase('es').includes(termino));
                option.classList.toggle('hidden', ocultar);
            });
            if (funcionarioId && reemplazante?.value === funcionarioId) {
                reemplazante.value = '';
                buscarReemplazante.value = '';
                reemplazanteConflicto.textContent = 'No puedes seleccionar como reemplazante al mismo funcionario.';
                buscarReemplazante.focus();
                window.setTimeout(() => { reemplazanteConflicto.textContent = ''; }, 4000);
            }
        }

        function normalizarRut(value) {
            const limpio = value.replace(/[^0-9kK]/g, '').toUpperCase();
            return limpio.length > 1 ? `${limpio.slice(0, -1)}-${limpio.slice(-1)}` : limpio;
        }

        unidad?.addEventListener('change', actualizarUnidad);
        funcionario?.addEventListener('change', () => {
            vinculo.value = funcionario.selectedOptions[0]?.dataset.vinculo || '';
            actualizarReemplazantes();
        });
        buscarReemplazante?.addEventListener('focus', () => {
            resultadosReemplazante.classList.remove('hidden');
            actualizarReemplazantes();
        });
        buscarReemplazante?.addEventListener('input', () => {
            reemplazante.value = '';
            personaExistenteMensaje.textContent = '';
            resultadosReemplazante.classList.remove('hidden');
            actualizarReemplazantes();
        });
        resultadosReemplazante?.addEventListener('click', (event) => {
            const option = event.target.closest('[data-persona]');
            if (!option || option.dataset.persona === funcionario.value) return;
            reemplazante.value = option.dataset.persona;
            buscarReemplazante.value = option.dataset.label;
            document.getElementById('ficha_reemplazante_nombre').textContent = option.dataset.label.split(' · ')[0];
            document.getElementById('ficha_reemplazante_rut').textContent = option.dataset.label.split(' · ')[1] || '';
            Alpine.$data(document.querySelector('form[x-data]')).seleccionado = true;
            if (option.dataset.estamento) document.getElementById('estamento_id').value = option.dataset.estamento;
            if (option.dataset.profesion) document.getElementById('profesion_id').value = option.dataset.profesion;
            if (option.dataset.cargo) document.getElementById('cargo_texto').value = option.dataset.cargo;
            reemplazante.dispatchEvent(new Event('change'));
            resultadosReemplazante.classList.add('hidden');
            actualizarReemplazantes();
        });
        document.addEventListener('click', (event) => {
            if (!buscarReemplazante.contains(event.target) && !resultadosReemplazante.contains(event.target)) resultadosReemplazante.classList.add('hidden');
        });
        nuevoRut?.addEventListener('input', () => {
            const rut = normalizarRut(nuevoRut.value);
            rutMensaje.textContent = '';
            rutMensaje.className = 'mt-1 text-xs';
            personaExistenteMensaje.textContent = '';
            if (rut && rut === funcionario.selectedOptions[0]?.dataset.rut) {
                rutMensaje.textContent = 'Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.';
                rutMensaje.classList.add('text-red-600');
                return;
            }
            const option = [...resultadosReemplazante.querySelectorAll('[data-rut]')].find((item) => item.dataset.rut === rut);
            if (!option) return;
            reemplazante.value = option.dataset.persona;
            buscarReemplazante.value = option.dataset.label;
            personaExistenteMensaje.textContent = 'Esta persona ya se encuentra registrada en el sistema. Se utilizará el registro existente.';
            reemplazante.dispatchEvent(new Event('change'));
        });
        actualizarUnidad();
    </script>
</x-app-layout>
