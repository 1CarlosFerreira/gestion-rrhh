<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Editar {{ $tramite->codigo }} · {{ $tramite->estadoTramite->nombre }}</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl space-y-4 px-4">
        @if(session('status'))<div class="rounded bg-green-50 p-3">{{ session('status') }}</div>@endif
        @if(session('upload_error'))<div class="rounded bg-red-50 p-3 text-red-800">{{ session('upload_error') }}</div>@endif
        <form method="POST" action="{{ route('reemplazos.update', $tramite) }}" class="space-y-6 rounded-lg bg-white p-6 shadow-sm" x-data="@js([
            'unidad' => (string) old('unidad_servicio_id', $tramite->unidad_servicio_id),
            'nuevo' => (bool) old('nuevo_reemplazante_rut'),
        ])">@csrf @method('PUT')
            <section>
                <h3 class="font-semibold">1. Origen del reemplazo</h3>
                <p class="text-sm">Unidad: <strong>{{ $tramite->unidadServicio->nombre }}</strong></p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <label>Tipo de reemplazo<select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($tiposReemplazo as $tipo)<option value="{{ $tipo->id }}" @selected($tramite->reemplazo->tipo_reemplazo_id === $tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select></label>
                    <label>Funcionario a reemplazar<select name="funcionario_id" id="funcionario_id" class="mt-1 w-full rounded border-gray-300"><option value="">Sin funcionario asociado</option>@foreach($funcionariosUnidad as $persona) @php($vinculo=$persona->vinculos->first())<option value="{{ $persona->id }}" data-vinculo="{{ $vinculo->id }}" data-rut="{{ $persona->rut }}" @selected($tramite->reemplazo->funcionario_id === $persona->id)>{{ $persona->nombre_completo }} · {{ \App\Support\Rut\Rut::format($persona->rut) }}</option>@endforeach</select><input type="hidden" name="funcionario_vinculo_id" id="funcionario_vinculo_id" value="{{ $tramite->reemplazo->funcionario_vinculo_id }}"></label>
                </div>
            </section>
            <section><h3 class="font-semibold">2. Justificación</h3><textarea name="justificacion" rows="4" class="mt-2 w-full rounded border-gray-300">{{ old('justificacion', $tramite->reemplazo->justificacion) }}</textarea></section>
            <section>
                <style>[x-cloak] { display: none !important; }</style>
                <h3 class="font-semibold">3. Reemplazante propuesto</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div x-show="! nuevo" class="md:col-span-2">
                    <label>Seleccione o escriba RUT/nombre del reemplazante
                        <x-text-input id="buscar_reemplazante" type="search" role="combobox" autocomplete="off" class="mt-1 w-full" :value="$tramite->reemplazo->reemplazante ? $tramite->reemplazo->reemplazante->nombre_completo.' · '.\App\Support\Rut\Rut::format($tramite->reemplazo->reemplazante->rut) : ''" placeholder="Escriba RUT, nombre o apellido"/>
                        <input type="hidden" name="reemplazante_id" id="reemplazante_id" x-ref="reemplazante" value="{{ old('reemplazante_id', $tramite->reemplazo->reemplazante_id) }}" @change="if ($event.target.value) { nuevo = false; $refs.rut.value = ''; $refs.nombres.value = ''; $refs.paterno.value = ''; $refs.materno.value = '' }">
                        <div id="resultados_reemplazante" class="mt-1 hidden max-h-56 overflow-y-auto rounded border bg-white">
                            @foreach($personas as $persona) @continue($tramite->reemplazo->funcionario_id === $persona->id) @php($antecedente = $persona->vinculos->first())
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
                    <section class="md:col-span-2"><h3 class="mb-2 font-semibold">4. Función y período propuesto</h3><div class="grid gap-3 md:grid-cols-2"><label>Estamento<select name="estamento_id" id="estamento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($estamentos as $item)<option value="{{ $item->id }}" @selected($tramite->reemplazo->estamento_id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label><label>Profesión<select name="profesion_id" id="profesion_id" class="mt-1 w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($profesiones as $item)<option value="{{ $item->id }}" @selected($tramite->reemplazo->profesion_id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></label><label>Cargo o función<x-text-input name="cargo_texto" id="cargo_texto" :value="$tramite->reemplazo->cargo_texto" class="mt-1 w-full"/></label><span></span><label>Fecha de inicio<x-text-input type="date" name="fecha_inicio" :value="$tramite->reemplazo->fecha_inicio?->format('Y-m-d')" class="mt-1 w-full"/></label><label>Fecha de término<x-text-input type="date" name="fecha_termino" :value="$tramite->reemplazo->fecha_termino?->format('Y-m-d')" class="mt-1 w-full"/></label><p class="rounded bg-gray-50 p-3 md:col-span-2">Unidad donde desempeñará funciones: <strong>{{ $tramite->unidadServicio->nombre }}</strong></p></div></section>
                </div>
            </section>
            <x-input-error :messages="$errors->all()"/>
            <div class="flex justify-end"><x-primary-button>Guardar borrador</x-primary-button></div>
        </form>
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="font-semibold">5. Documentos de respaldo</h3>
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
            <h4 class="mt-5 font-medium">+ Adjuntar otro documento</h4>
            <p class="mt-1 text-sm text-gray-600">Adjunte los antecedentes necesarios para respaldar la solicitud.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('tramites.adjuntos.store', $tramite) }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
                <input type="file" name="archivo" required class="rounded border p-2 md:col-span-2">
                <select name="tipo_documento_id" class="rounded border-gray-300"><option value="">Sin clasificación</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select>
                <x-primary-button>+ Adjuntar documento</x-primary-button>
            </form>
        </section>
        <div class="flex justify-end"><form method="POST" action="{{ route('reemplazos.send', $tramite) }}">@csrf<x-secondary-button>Enviar a Gestión de Personas</x-secondary-button></form></div>
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

        function actualizarReemplazantes() {
            const funcionarioId = funcionario?.value || '';
            const termino = buscar?.value.toLocaleLowerCase('es') || '';
            resultados?.querySelectorAll('[data-persona]').forEach((option) => {
                const ocultar = option.dataset.persona === funcionarioId || (termino && !option.textContent.toLocaleLowerCase('es').includes(termino));
                option.classList.toggle('hidden', ocultar);
            });
            reemplazanteConflicto.textContent = '';
            if (reemplazante?.value === funcionarioId) {
                reemplazante.value = '';
                buscar.value = '';
                reemplazanteConflicto.textContent = 'El reemplazante seleccionado coincide con el funcionario a reemplazar y fue eliminado de la selección.';
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
        actualizarReemplazantes();
    </script>
</x-app-layout>
