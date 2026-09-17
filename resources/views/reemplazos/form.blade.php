<x-app-layout><x-slot name="header"><div class="flex items-center justify-between"><div><h2 class="text-xl font-semibold">{{ $tramite ? $tramite->codigo : 'Nueva Solicitud de Reemplazo' }}</h2><p class="text-sm text-gray-500">Estado: {{ $tramite?->estadoTramite?->nombre ?? 'Borrador' }} @if($tramite) · {{ $tramite->unidadOrganizacional->nombre }} @endif</p></div><div class="text-right"><div class="flex gap-3"><button type="submit" form="reemplazo-form" class="rounded bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">{{ $tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Guardar cambios' : 'Guardar borrador' }}</button>@if($tramite)<button type="submit" form="reemplazo-form" formaction="{{ route('reemplazos.send',$tramite) }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $tramite->estadoTramite->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Reenviar a Gestión de Personas' : 'Enviar a Gestión de Personas' }}</button>@endif</div>@if($tramite)<p class="mt-1 text-xs text-gray-500">Al enviar se guardan los cambios actuales y luego se validan los antecedentes.</p>@endif</div></div></x-slot>
<div class="py-10"><div class="mx-auto max-w-7xl px-4">@if(session('status'))<div class="mb-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mb-4 rounded bg-red-50 p-3 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @if($tramite)<form id="adjunto-form" method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.adjuntos.store',$tramite) }}">@csrf</form>@endif
<form id="reemplazo-form" method="POST" action="{{ $tramite ? route('reemplazos.update',$tramite) : route('reemplazos.store') }}" class="grid gap-5 lg:grid-cols-2">@csrf @if($tramite)@method('PUT')@endif
@php
    $unidadInicial = (string) old('unidad_organizacional_id', $tramite?->unidad_organizacional_id ?? '');
    $funcionarioInicial = (string) old('funcionario_id', $detalle?->funcionario_id ?? '');
    $fechaFuncionarioInicial = old('fecha_funcionario_desde', $detalle?->fecha_funcionario_desde?->toDateString() ?? '');
    $funcionariosIniciales = $funcionarios->map(function ($persona) {
        $vinculo = $persona->vinculosDotacion->first();

        return [
            'id' => (string) $persona->id,
            'nombre' => $persona->nombre_completo,
            'rut' => $persona->rut,
            'antecedente_laboral' => $vinculo ? [
                'estamento' => $vinculo->estamento?->nombre,
                'profesion' => $vinculo->profesion?->nombre,
                'calidad_contractual' => $vinculo->calidadContractual?->nombre,
                'cargo_funcion' => $vinculo->cargo_funcion,
                'unidad' => $vinculo->unidad?->nombre,
            ] : null,
        ];
    })->values();
@endphp
<section
    class="space-y-4 rounded-xl bg-white p-6 shadow"
    x-data="{
        unidadId: @js($unidadInicial),
        funcionarioId: @js($funcionarioInicial),
        fechaFuncionario: @js($fechaFuncionarioInicial),
        funcionarios: @js($funcionariosIniciales),
        cargando: false,
        mensaje: '',
        solicitud: 0,
        textoOpcion() {
            if (this.cargando) return 'Cargando funcionarios...'
            if (! this.unidadId) return 'Pendiente'
            if (this.mensaje) return this.mensaje
            return 'Seleccione'
        },
        funcionarioSeleccionado() {
            return this.funcionarios.find((persona) => persona.id === String(this.funcionarioId)) || null
        },
        async cargarFuncionarios() {
            const solicitudActual = ++this.solicitud
            const funcionarioSeleccionado = this.funcionarioId
            const funcionariosAnteriores = this.funcionarios
            this.mensaje = ''

            if (! this.unidadId) {
                this.funcionarioId = ''
                this.funcionarios = []
                this.cargando = false
                return
            }

            this.cargando = true
            const url = new URL(@js(route('reemplazos.funcionarios')), window.location.origin)
            url.searchParams.set('unidad_organizacional_id', this.unidadId)
            if (this.fechaFuncionario) url.searchParams.set('fecha', this.fechaFuncionario)

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } })
                if (! response.ok) throw new Error('No fue posible consultar la dotación.')
                const funcionarios = await response.json()
                if (solicitudActual !== this.solicitud) return
                this.funcionarios = funcionarios.map((persona) => ({ ...persona, id: String(persona.id) }))
                this.funcionarioId = this.funcionarios.some((persona) => persona.id === String(funcionarioSeleccionado))
                    ? String(funcionarioSeleccionado)
                    : ''
                if (this.funcionarios.length === 0) this.mensaje = 'No hay funcionarios vigentes en esta unidad.'
            } catch (error) {
                if (solicitudActual !== this.solicitud) return
                this.funcionarios = funcionariosAnteriores
                this.funcionarioId = funcionarioSeleccionado
                this.mensaje = 'No fue posible cargar los funcionarios.'
            } finally {
                if (solicitudActual === this.solicitud) this.cargando = false
            }
        }
    }"
    x-init="$nextTick(() => funcionarioId = @js($funcionarioInicial))"
>
    <h3 class="font-semibold">Origen del reemplazo</h3>
    <label class="block">
        Unidad
        <select name="unidad_organizacional_id" x-model="unidadId" x-on:change="cargarFuncionarios()" class="mt-1 w-full rounded border-gray-300" required>
            <option value="">Seleccione</option>
            @foreach($unidades as $unidad)
                <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('unidad_organizacional_id')" class="mt-1" />
    </label>

    <template x-if="funcionarioSeleccionado()?.antecedente_laboral">
        <div class="rounded-lg border border-gray-200 bg-gray-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ficha laboral vigente</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900" x-text="funcionarioSeleccionado().nombre"></p>
                    <p class="font-mono text-xs text-gray-500" x-text="funcionarioSeleccionado().rut"></p>
                </div>
                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600">Solo informativo</span>
            </div>
            <dl class="mt-3 grid gap-x-4 gap-y-2 border-t border-gray-200 pt-3 text-xs sm:grid-cols-2">
                <div><dt class="text-gray-500">Estamento</dt><dd class="mt-0.5 font-medium text-gray-800" x-text="funcionarioSeleccionado().antecedente_laboral.estamento || 'No informado'"></dd></div>
                <div><dt class="text-gray-500">Profesión</dt><dd class="mt-0.5 font-medium text-gray-800" x-text="funcionarioSeleccionado().antecedente_laboral.profesion || 'No informada'"></dd></div>
                <div><dt class="text-gray-500">Calidad contractual</dt><dd class="mt-0.5 font-medium text-gray-800" x-text="funcionarioSeleccionado().antecedente_laboral.calidad_contractual || 'No informada'"></dd></div>
                <div><dt class="text-gray-500">Cargo / función</dt><dd class="mt-0.5 font-medium text-gray-800" x-text="funcionarioSeleccionado().antecedente_laboral.cargo_funcion || 'No informado'"></dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">Unidad</dt><dd class="mt-0.5 font-medium text-gray-800" x-text="funcionarioSeleccionado().antecedente_laboral.unidad || 'No informada'"></dd></div>
            </dl>
        </div>
    </template>
    <p class="text-xs text-gray-500">La dotación vigente se carga automáticamente según la unidad y la fecha inicial.</p>

    <label class="block">
        Funcionario reemplazado
        <select name="funcionario_id" x-model="funcionarioId" x-bind:disabled="cargando || ! unidadId" class="mt-1 w-full rounded border-gray-300 disabled:bg-gray-100 disabled:text-gray-500">
            <option value="" x-text="textoOpcion()"></option>
            <template x-for="persona in funcionarios" x-bind:key="persona.id">
                <option x-bind:value="persona.id" x-text="`${persona.nombre} · ${persona.rut}`"></option>
            </template>
        </select>
        <x-input-error :messages="$errors->get('funcionario_id')" class="mt-1" />
    </label>

    <label class="block">Tipo de reemplazo<select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($tipos as $tipo)<option value="{{ $tipo->id }}" @selected(old('tipo_reemplazo_id',$detalle?->tipo_reemplazo_id)==$tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('tipo_reemplazo_id')" class="mt-1" /></label><h4 class="font-medium">Período del funcionario reemplazado</h4><div class="grid grid-cols-2 gap-3"><label>Desde<x-text-input type="date" class="mt-1 w-full" name="fecha_funcionario_desde" x-model="fechaFuncionario" x-on:change="if (unidadId) cargarFuncionarios()" value="{{ $fechaFuncionarioInicial }}"/><x-input-error :messages="$errors->get('fecha_funcionario_desde')" class="mt-1" /></label><label>Hasta<x-text-input type="date" class="mt-1 w-full" name="fecha_funcionario_hasta" value="{{ old('fecha_funcionario_hasta',$detalle?->fecha_funcionario_hasta?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_funcionario_hasta')" class="mt-1" /></label></div>
</section>
@php
    $antecedentesPorPersona = $personas->mapWithKeys(fn ($persona) => [(string) $persona->id => $persona->vinculosDotacion->map(fn ($vinculo) => [
        'id' => (string) $vinculo->id,
        'estamento_id' => (string) $vinculo->estamento_id,
        'profesion_id' => $vinculo->profesion_id ? (string) $vinculo->profesion_id : '',
        'calidad_contractual_id' => (string) $vinculo->calidad_contractual_id,
        'cargo_funcion' => $vinculo->cargo_funcion,
        'estamento' => $vinculo->estamento?->nombre,
        'profesion' => $vinculo->profesion?->nombre,
        'calidad_contractual' => $vinculo->calidadContractual?->nombre,
        'unidad' => $vinculo->unidad?->nombre,
        'vigencia' => $vinculo->vigente_desde?->format('d/m/Y').' — '.($vinculo->vigente_hasta?->format('d/m/Y') ?? 'Actualidad'),
    ])->values()]);
@endphp
<section class="space-y-4 rounded-xl bg-white p-6 shadow" x-data="{
    personaId: @js((string) old('reemplazante_id', $detalle?->reemplazante_id ?? '')),
    antecedentes: @js($antecedentesPorPersona),
    vinculoBase: '',
    estamentoId: @js((string) old('reemplazante_estamento_id', $detalle?->reemplazante_estamento_id ?? '')),
    profesionId: @js((string) old('reemplazante_profesion_id', $detalle?->reemplazante_profesion_id ?? '')),
    calidadId: @js((string) old('reemplazante_calidad_contractual_id', $detalle?->reemplazante_calidad_contractual_id ?? '')),
    cargo: @js((string) old('reemplazante_cargo_funcion', $detalle?->reemplazante_cargo_funcion ?? '')),
    nuevoAbierto: @js(old('nuevo_reemplazante_rut') !== null),
    disponibles() { return this.antecedentes[this.personaId] || [] },
    cambiarPersona() {
        this.vinculoBase = ''
        this.estamentoId = ''
        this.profesionId = ''
        this.calidadId = ''
        this.cargo = ''
    },
    copiarAntecedentes() {
        const base = this.disponibles().find((item) => item.id === this.vinculoBase)
        if (! base) return
        this.estamentoId = base.estamento_id
        this.profesionId = base.profesion_id
        this.calidadId = base.calidad_contractual_id
        this.cargo = base.cargo_funcion
    }
}">
    <div class="flex items-center justify-between gap-3">
        <h3 class="font-semibold">Reemplazante</h3>
        <button type="button" x-on:click="nuevoAbierto = ! nuevoAbierto" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:border-indigo-300 hover:text-indigo-700" x-bind:aria-expanded="nuevoAbierto">
            <span aria-hidden="true">+</span> Nuevo reemplazante
        </button>
    </div>
    <label class="block">Persona existente
        <select name="reemplazante_id" x-model="personaId" x-on:change="cambiarPersona()" class="mt-1 w-full rounded border-gray-300">
            <option value="">Pendiente / registrar nueva</option>
            @foreach($personas as $persona)<option value="{{ $persona->id }}">{{ $persona->nombre_completo }} · {{ $persona->rut }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('reemplazante_id')" class="mt-1" />
    </label>
    <div class="flex justify-center text-gray-300" aria-hidden="true">↓</div>
    <div x-show="personaId && disponibles().length" class="rounded-lg border border-gray-200 bg-gray-50/60 px-3 py-2.5">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Antecedentes laborales conocidos</p>
            <p class="text-xs text-gray-500" x-text="`${disponibles().length} ${disponibles().length === 1 ? 'registro encontrado' : 'registros encontrados'}`"></p>
        </div>
        <div class="mt-2 divide-y divide-gray-200 border-t border-gray-200">
            <template x-for="item in disponibles()" x-bind:key="item.id">
                <article class="-mx-1 px-2 py-2.5 transition" x-bind:class="vinculoBase === item.id ? 'bg-indigo-50/70' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900"><span x-text="item.profesion || 'Sin profesión informada'"></span><span class="font-normal text-gray-400"> · </span><span x-text="item.estamento || 'Sin estamento informado'"></span></p>
                            <p class="mt-0.5 truncate text-xs text-gray-600"><span x-text="item.calidad_contractual || 'Calidad no informada'"></span><span class="text-gray-400"> · </span><span x-text="item.cargo_funcion || 'Cargo no informado'"></span></p>
                            <p class="mt-0.5 truncate text-xs text-gray-500"><span x-text="item.unidad || 'Unidad no informada'"></span><span class="text-gray-400"> · </span><span x-text="item.vigencia"></span></p>
                        </div>
                        <button type="button" x-on:click="vinculoBase = item.id; copiarAntecedentes()" class="shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold" x-bind:class="vinculoBase === item.id ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-indigo-700 hover:border-indigo-300'" x-text="vinculoBase === item.id ? 'Base seleccionada' : 'Usar como base'"></button>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <p x-show="personaId && ! disponibles().length" class="border-y border-gray-200 bg-gray-50/60 px-3 py-2.5 text-sm text-gray-600">Sin antecedentes laborales registrados.</p>
    <div x-show="nuevoAbierto" class="border-y border-gray-200 bg-gray-50/60 px-3 py-3"><p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-600">Registrar nuevo reemplazante</p><div class="grid gap-3"><x-text-input name="nuevo_reemplazante_rut" value="{{ old('nuevo_reemplazante_rut') }}" placeholder="RUT"/><x-text-input name="nuevo_reemplazante_nombres" value="{{ old('nuevo_reemplazante_nombres') }}" placeholder="Nombres"/><div class="grid grid-cols-2 gap-2"><x-text-input name="nuevo_reemplazante_apellido_paterno" value="{{ old('nuevo_reemplazante_apellido_paterno') }}" placeholder="Apellido paterno"/><x-text-input name="nuevo_reemplazante_apellido_materno" value="{{ old('nuevo_reemplazante_apellido_materno') }}" placeholder="Apellido materno"/></div></div></div>
    <div class="flex justify-center text-gray-300" aria-hidden="true">↓</div>
    <fieldset class="grid gap-3 sm:grid-cols-2">
        <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-700">Antecedentes para este reemplazo</legend>
        <label class="text-sm">Estamento *<select name="reemplazante_estamento_id" x-model="estamentoId" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($estamentos as $estamento)<option value="{{ $estamento->id }}">{{ $estamento->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_estamento_id')" class="mt-1" /></label>
        <label class="text-sm">Profesión<select name="reemplazante_profesion_id" x-model="profesionId" class="mt-1 w-full rounded border-gray-300"><option value="">No corresponde / pendiente</option>@foreach($profesiones as $profesion)<option value="{{ $profesion->id }}">{{ $profesion->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_profesion_id')" class="mt-1" /></label>
        <label class="text-sm">Calidad contractual *<select name="reemplazante_calidad_contractual_id" x-model="calidadId" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($calidades as $calidad)<option value="{{ $calidad->id }}">{{ $calidad->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_calidad_contractual_id')" class="mt-1" /></label>
        <label class="text-sm">Cargo / función *<input name="reemplazante_cargo_funcion" x-model="cargo" maxlength="200" class="mt-1 w-full rounded border-gray-300"><x-input-error :messages="$errors->get('reemplazante_cargo_funcion')" class="mt-1" /></label>
        <p class="text-xs text-gray-500 sm:col-span-2">Estos datos son una propuesta de esta solicitud. Guardar el borrador no crea ni modifica Dotación.</p>
    </fieldset>
    <h4 class="font-medium">Período efectivo del reemplazante</h4><div class="grid grid-cols-2 gap-3"><label>Desde<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_desde" value="{{ old('fecha_reemplazante_desde',$detalle?->fecha_reemplazante_desde?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_desde')" class="mt-1" /></label><label>Hasta<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_hasta" value="{{ old('fecha_reemplazante_hasta',$detalle?->fecha_reemplazante_hasta?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_hasta')" class="mt-1" /></label></div>@if($detalle && $detalle->diasFuncionario() > 0)<div class="rounded bg-slate-50 p-3 text-sm"><p>Período funcionario: {{ $detalle->diasFuncionario() }} días calendario</p><p>Período reemplazante: {{ $detalle->diasReemplazante() }} días calendario</p><p>Días sin cobertura: {{ $detalle->diasSinCobertura() }}</p>@if($detalle->coberturaParcial())<p class="mt-2 rounded bg-amber-50 p-2 text-amber-800">El reemplazante cubrirá {{ $detalle->diasReemplazante() }} de los {{ $detalle->diasFuncionario() }} días del período solicitado. Quedarán {{ $detalle->diasSinCobertura() }} días sin cobertura.</p>@endif</div>@endif
</section>
<section class="rounded-xl bg-white p-6 shadow"><h3 class="mb-3 font-semibold">Justificación</h3><textarea name="justificacion" rows="8" class="w-full rounded border-gray-300" placeholder="Puede completarse progresivamente">{{ old('justificacion',$detalle?->justificacion) }}</textarea><x-input-error :messages="$errors->get('justificacion')" class="mt-1" /></section>
<section class="rounded-xl bg-white p-6 shadow"><h3 class="mb-3 font-semibold">Documentos</h3>@if(!$tramite)<p class="text-sm text-gray-500">Guarde primero el borrador para habilitar adjuntos.</p>@else @can('tramites.adjuntos.cargar')<div class="space-y-3"><input form="adjunto-form" type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required>@error('archivo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<select form="adjunto-form" name="tipo_documento_id" class="w-full rounded border-gray-300"><option value="">Sin tipo documental</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select><x-secondary-button type="submit" form="adjunto-form">Adjuntar</x-secondary-button></div>@endcan<div class="mt-4 space-y-2">@forelse($tramite->adjuntos as $adjunto)<p class="text-sm">{{ $adjunto->original_name }} · v{{ $adjunto->version }} · {{ $adjunto->status }}</p>@empty<p class="text-sm text-gray-500">Sin documentos adjuntos.</p>@endforelse</div>@endif</section>
</form>@if($tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION')@php($devolucion=$tramite->historial()->where('action_code','DEVOLVER_PARA_CORRECCION')->latest('occurred_at')->first())@if($devolucion)<section class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><h3 class="font-semibold">Última observación de devolución</h3><p class="mt-2">{{ $devolucion->observation }}</p></section>@endif @endif</div></div></x-app-layout>
