<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Nueva Solicitud de Reemplazo</h2>
                <p class="mt-1 text-sm text-gray-600">Complete los antecedentes de la solicitud. Puede guardar el borrador y continuar más tarde.</p>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">Estado: Nueva solicitud</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.store') }}" class="space-y-6 rounded-lg bg-white p-6 shadow-sm" x-data="@js([
                'unidad' => (string) old('unidad_servicio_id', ''),
                'nuevo' => (bool) old('nuevo_reemplazante_rut'),
            ])">
                @csrf

                <section>
                    <h3 class="font-semibold text-gray-900">1. Origen del reemplazo</h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <label>Unidad/Servicio
                            <select name="unidad_servicio_id" x-model="unidad" required class="mt-1 w-full rounded border-gray-300">
                                <option value="">Seleccione</option>
                                @foreach($unidades as $unidad)
                                    <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Tipo de reemplazo
                            <select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300">
                                <option value="">Seleccione</option>
                                @foreach($tiposReemplazo as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((int) old('tipo_reemplazo_id') === $tipo->id)>{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="md:col-span-2">Funcionario a reemplazar
                            <select name="funcionario_id" id="funcionario_id" class="mt-1 w-full rounded border-gray-300" :disabled="!unidad">
                                <option value="">Seleccione una unidad o deje sin funcionario asociado</option>
                                @foreach($funcionariosUnidad as $persona)
                                    @foreach($persona->vinculos as $vinculo)
                                        <option value="{{ $persona->id }}" data-unidad="{{ $vinculo->unidad_servicio_id }}" data-vinculo="{{ $vinculo->id }}" data-rut="{{ $persona->rut }}" x-show="unidad === '{{ $vinculo->unidad_servicio_id }}'" @selected((int) old('funcionario_id') === $persona->id)>{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <input type="hidden" name="funcionario_vinculo_id" id="funcionario_vinculo_id" value="{{ old('funcionario_vinculo_id') }}">
                            <p class="mt-1 text-xs text-gray-500">Se muestra únicamente la dotación activa y vigente de la unidad seleccionada.</p>
                        </label>
                    </div>
                </section>

                <section>
                    <h3 class="font-semibold text-gray-900">2. Justificación</h3>
                    <textarea name="justificacion" rows="4" class="mt-2 w-full rounded border-gray-300">{{ old('justificacion') }}</textarea>
                </section>

                <section>
                    <style>[x-cloak] { display: none !important; }</style>
                    <h3 class="font-semibold text-gray-900">3. Reemplazante propuesto</h3>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div x-show="! nuevo" class="md:col-span-2">
                        <label>Seleccione o escriba RUT/nombre del reemplazante
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
                        <div class="md:col-span-2"><button type="button" @click="nuevo = ! nuevo; if (nuevo) { $refs.reemplazante.value = ''; document.getElementById('buscar_reemplazante').value = ''; document.getElementById('resultados_reemplazante').classList.add('hidden'); } else { $refs.rut.value = ''; $refs.nombres.value = ''; $refs.paterno.value = ''; $refs.materno.value = '' }" class="text-sm font-medium text-blue-700" x-text="nuevo ? 'Cancelar' : '+ Agregar reemplazante que no está en la lista'">+ Agregar reemplazante que no está en la lista</button></div>
                        <div x-show="nuevo" x-cloak class="contents">
                            <div><x-text-input id="nuevo_reemplazante_rut" x-ref="rut" name="nuevo_reemplazante_rut" :value="old('nuevo_reemplazante_rut')" placeholder="RUT"/><p id="rut_reemplazante_mensaje" class="mt-1 text-xs"></p></div>
                            <x-text-input x-ref="nombres" name="nuevo_reemplazante_nombres" :value="old('nuevo_reemplazante_nombres')" placeholder="Nombres"/>
                            <x-text-input x-ref="paterno" name="nuevo_reemplazante_apellido_paterno" :value="old('nuevo_reemplazante_apellido_paterno')" placeholder="Apellido paterno"/>
                            <x-text-input x-ref="materno" name="nuevo_reemplazante_apellido_materno" :value="old('nuevo_reemplazante_apellido_materno')" placeholder="Apellido materno (opcional)"/>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="font-semibold text-gray-900">4. Función y período propuesto</h3>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <label>Estamento<select name="estamento_id" id="estamento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($estamentos as $item)<option value="{{ $item->id }}" @selected((int) old('estamento_id') === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                        <label>Profesión<select name="profesion_id" id="profesion_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($profesiones as $item)<option value="{{ $item->id }}" @selected((int) old('profesion_id') === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
                        <label>Cargo o función<x-text-input name="cargo_texto" id="cargo_texto" :value="old('cargo_texto')" class="mt-1 w-full"/></label><span></span>
                        <label>Fecha de inicio<x-text-input type="date" name="fecha_inicio" :value="old('fecha_inicio')" class="mt-1 w-full"/></label>
                        <label>Fecha de término<x-text-input type="date" name="fecha_termino" :value="old('fecha_termino')" class="mt-1 w-full"/></label>
                        <p class="rounded bg-gray-50 p-3 md:col-span-2">Unidad destino: <strong id="unidad_destino">Se completará al seleccionar la unidad</strong></p>
                    </div>
                </section>

                <section class="rounded border border-blue-100 bg-blue-50 p-4">
                    <h3 class="font-semibold text-gray-900">5. Documentos de respaldo</h3>
                    <p class="mt-1 text-sm text-gray-700">Adjunte los antecedentes necesarios para respaldar la solicitud.</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <input type="file" name="archivo" class="rounded border bg-white p-2 md:col-span-2">
                        <select name="tipo_documento_id" class="rounded border-gray-300"><option value="">Sin clasificación</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select>
                    </div>
                    <button type="submit" name="accion" value="adjuntar" class="mt-3 text-sm font-medium text-blue-700">+ Adjuntar documento</button>
                </section>

                <x-input-error :messages="$errors->all()"/>
                <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-white/95 p-3 shadow-sm backdrop-blur">
                    <span class="text-sm font-medium text-gray-700">Estado: Nueva solicitud</span>
                    <x-primary-button name="accion" value="guardar">Guardar borrador</x-primary-button>
                </div>
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
            destino.textContent = unidadId ? unidad.selectedOptions[0].text : 'Se completará al seleccionar la unidad';
            actualizarReemplazantes();
        }

        function actualizarReemplazantes() {
            const funcionarioId = funcionario?.value || '';
            const termino = buscarReemplazante?.value.toLocaleLowerCase('es') || '';
            resultadosReemplazante?.querySelectorAll('[data-persona]').forEach((option) => {
                const ocultar = option.dataset.persona === funcionarioId || (termino !== '' && !option.textContent.toLocaleLowerCase('es').includes(termino));
                option.classList.toggle('hidden', ocultar);
            });
            reemplazanteConflicto.textContent = '';
            if (reemplazante?.value === funcionarioId) {
                reemplazante.value = '';
                buscarReemplazante.value = '';
                reemplazanteConflicto.textContent = 'El reemplazante seleccionado coincide con el funcionario a reemplazar y fue eliminado de la selección.';
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
