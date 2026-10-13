<x-app-layout><x-slot name="header"><div class="flex items-center justify-between"><div><h2 class="text-xl font-semibold">{{ $tramite ? $tramite->codigo : 'Nueva Solicitud de Reemplazo' }}</h2><p class="text-sm text-gray-500">Estado: {{ $tramite?->estadoTramite?->nombre ?? 'Borrador' }} @if($tramite) · {{ $tramite->unidadOrganizacional->nombre }} @endif · {{ $legacy ? 'Histórica V2' : 'Transitoria V3' }}</p></div><div class="text-right"><div class="flex gap-3"><button type="submit" form="reemplazo-form" class="rounded bg-brand-primary px-4 py-2 text-sm font-semibold text-white">{{ $tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Guardar cambios' : 'Guardar borrador' }}</button>@if($tramite)<button type="submit" form="reemplazo-form" formaction="{{ route('reemplazos.send',$tramite) }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $tramite->estadoTramite->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Reenviar a Gestión de Personas' : 'Enviar a Gestión de Personas' }}</button>@endif</div>@if($tramite)<p class="mt-1 text-xs text-gray-500">Al enviar se guardan los cambios actuales y luego se validan los antecedentes.</p>@endif</div></div></x-slot>
<div class="py-10"><div class="mx-auto max-w-7xl px-4">@if(session('status'))<div class="mb-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mb-4 rounded bg-red-50 p-3 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($tramite)
    <form id="adjunto-form" method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.adjuntos.store', $tramite) }}">@csrf</form>
    @foreach($tramite->adjuntos as $adjunto)
        @if($adjunto->status === 'ACTIVO' && $adjunto->documentoGenerado === null)
            <form id="adjunto-version-{{ $adjunto->id }}" method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.adjuntos.version', [$tramite, $adjunto]) }}">@csrf</form>
            <form id="adjunto-anular-{{ $adjunto->id }}" method="POST" action="{{ route('reemplazos.adjuntos.annul', [$tramite, $adjunto]) }}">@csrf @method('PATCH')</form>
        @endif
    @endforeach
@endif
<form id="reemplazo-form" method="POST" action="{{ $tramite ? route('reemplazos.update',$tramite) : route('reemplazos.store') }}" class="grid gap-5 lg:grid-cols-2">@csrf @if($tramite)@method('PUT')@endif
@php
    $unidadInicial = (string) old($legacy ? 'unidad_organizacional_id' : 'unidad_origen_id', $solicitudContrato?->unidad_origen_id ?? $tramite?->unidad_organizacional_id ?? '');
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
            url.searchParams.set(@js($legacy ? 'unidad_organizacional_id' : 'unidad_origen_id'), this.unidadId)
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
    @unless($legacy)
    <h3 class="font-semibold">Antecedentes de la solicitud</h3>
    <label class="block">
        Unidad solicitante
        <select name="unidad_solicitante_id" class="mt-1 w-full rounded border-gray-300" required>
            <option value="">Seleccione</option>
            @foreach($unidades as $unidad)
                <option value="{{ $unidad->id }}" @selected((string) old('unidad_solicitante_id', $solicitudContrato?->unidad_solicitante_id ?? '') === (string) $unidad->id)>{{ $unidad->nombre }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('unidad_solicitante_id')" class="mt-1" />
    </label>
    @endunless
    @if($legacy)<h3 class="font-semibold">Origen del reemplazo</h3>@endif
    @unless($legacy)<fieldset class="space-y-3 rounded-lg border border-gray-200 p-4"><legend class="px-1 text-sm font-semibold text-gray-800">Funcionario a reemplazar</legend>@endunless
    <label class="block">
        {{ $legacy ? 'Unidad' : 'Unidad de dotación del funcionario a reemplazar' }}
        <select name="{{ $legacy ? 'unidad_organizacional_id' : 'unidad_origen_id' }}" x-model="unidadId" x-on:change="cargarFuncionarios()" class="mt-1 w-full rounded border-gray-300" required>
            <option value="">Seleccione</option>
            @foreach($unidades as $unidad)
                <option value="{{ $unidad->id }}">{{ $unidad->nombre }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get($legacy ? 'unidad_organizacional_id' : 'unidad_origen_id')" class="mt-1" />
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
    <p class="text-xs text-gray-500">La unidad origen identifica la dotación del funcionario. Puede ser distinta de la unidad solicitante y del destino funcional. La lista se actualiza según esa unidad y la fecha inicial.</p>

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
    @unless($legacy)</fieldset>@endunless

    <label class="block">Tipo de reemplazo<select name="tipo_reemplazo_id" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($tipos as $tipo)<option value="{{ $tipo->id }}" @selected(old('tipo_reemplazo_id',$detalle?->tipo_reemplazo_id)==$tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('tipo_reemplazo_id')" class="mt-1" /></label><h4 class="font-medium">Período de ausencia del funcionario</h4><div class="grid grid-cols-2 gap-3"><label>Desde<x-text-input type="date" class="mt-1 w-full" name="fecha_funcionario_desde" x-model="fechaFuncionario" x-on:change="if (unidadId) cargarFuncionarios()" value="{{ $fechaFuncionarioInicial }}"/><x-input-error :messages="$errors->get('fecha_funcionario_desde')" class="mt-1" /></label><label>Hasta<x-text-input type="date" class="mt-1 w-full" name="fecha_funcionario_hasta" value="{{ old('fecha_funcionario_hasta',$detalle?->fecha_funcionario_hasta?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_funcionario_hasta')" class="mt-1" /></label></div>
    <label class="block">Justificación<textarea name="justificacion" rows="5" class="mt-1 w-full rounded border-gray-300" placeholder="Puede completarse progresivamente">{{ old('justificacion',$detalle?->justificacion) }}</textarea><x-input-error :messages="$errors->get('justificacion')" class="mt-1" /></label>
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
    $hoyReemplazante = today();
    $personasOpciones = $personas->map(function ($persona) use ($hoyReemplazante) {
        $unidadesVigentes = $persona->vinculosDotacion
            ->filter(fn ($vinculo) => $vinculo->vigente_desde->lte($hoyReemplazante) && ($vinculo->vigente_hasta === null || $vinculo->vigente_hasta->gte($hoyReemplazante)))
            ->map(fn ($vinculo) => $vinculo->unidad?->nombre)
            ->filter()->unique()->values();

        return ['id' => (string) $persona->id, 'nombre' => $persona->nombre_completo, 'rut' => $persona->rut, 'unidad_vigente' => $unidadesVigentes->implode(' · ') ?: null];
    })->values();
    $modoSolicitado = old('modo_reemplazante', session('modo_reemplazante'));
    $modoReemplazanteInicial = $modoSolicitado === 'nuevo'
        || ($modoSolicitado === null && (filled(old('nuevo_reemplazante_rut')) || filled(old('nuevo_reemplazante_nombres'))))
        ? 'nuevo' : 'existente';
@endphp
<section class="space-y-4 rounded-xl bg-white p-6 shadow" x-data="{
    legacy: @js($legacy),
    modo: @js($modoReemplazanteInicial),
    personaId: @js((string) old('reemplazante_id', $detalle?->reemplazante_id ?? '')),
    antecedentes: @js($antecedentesPorPersona),
    opcionesBase: @js($personasOpciones),
    opciones: @js($legacy ? $personasOpciones : []),
    seleccionada: null,
    buscar: @js((string) old('buscar_reemplazante', old('buscar_reemplazante_rut', ''))),
    buscando: false,
    mensajeBusqueda: '',
    sinCoincidencias: false,
    mensajeRut: '',
    solicitudBusqueda: 0,
    solicitudRut: 0,
    rutNuevo: @js((string) old('nuevo_reemplazante_rut', '')),
    nombresNuevos: @js((string) old('nuevo_reemplazante_nombres', '')),
    apellidoPaternoNuevo: @js((string) old('nuevo_reemplazante_apellido_paterno', '')),
    apellidoMaternoNuevo: @js((string) old('nuevo_reemplazante_apellido_materno', '')),
    vinculoBase: '',
    estamentoId: @js((string) old('reemplazante_estamento_id', $detalle?->reemplazante_estamento_id ?? '')),
    profesionId: @js((string) old('reemplazante_profesion_id', $detalle?->reemplazante_profesion_id ?? '')),
    calidadId: @js((string) old('reemplazante_calidad_contractual_id', $detalle?->reemplazante_calidad_contractual_id ?? '')),
    cargo: @js((string) old('reemplazante_cargo_funcion', $detalle?->reemplazante_cargo_funcion ?? '')),
    nuevoAbierto: @js(filled(old('nuevo_reemplazante_rut'))),
    disponibles() { return this.antecedentes[this.personaId] || [] },
    opcionesVisibles() {
        if (this.seleccionada && ! this.opciones.some((persona) => persona.id === this.seleccionada.id)) return [this.seleccionada, ...this.opciones]
        return this.opciones
    },
    reiniciarBusqueda() {
        this.solicitudBusqueda++
        this.opciones = []
        this.sinCoincidencias = false
        this.mensajeBusqueda = ''
        this.buscando = false
    },
    async buscarPersona() {
        if (! this.legacy && this.modo !== 'existente') return
        const texto = this.buscar.trim()
        const solicitudActual = ++this.solicitudBusqueda
        this.sinCoincidencias = false
        if (texto.length < 3) {
            this.opciones = this.legacy ? this.opcionesBase : []
            this.mensajeBusqueda = ''
            this.buscando = false
            return
        }
        this.buscando = true
        const url = new URL(@js(route('reemplazos.personas')), window.location.origin)
        url.searchParams.set(this.legacy ? 'rut' : 'buscar', texto)
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } })
            if (! response.ok) throw new Error('No fue posible buscar personas.')
            const personas = await response.json()
            if (solicitudActual !== this.solicitudBusqueda) return
            this.opciones = personas.map((persona) => ({ id: persona.id === null ? null : String(persona.id), nombre: persona.nombre, rut: persona.rut, unidad_vigente: persona.unidad_vigente, inactiva: persona.inactiva === true }))
            this.sinCoincidencias = personas.length === 0
            this.mensajeBusqueda = this.sinCoincidencias ? 'No se encontraron personas activas.' : ''
        } catch (error) {
            if (solicitudActual === this.solicitudBusqueda) this.mensajeBusqueda = 'No fue posible buscar personas.'
        } finally {
            if (solicitudActual === this.solicitudBusqueda) this.buscando = false
        }
    },
    async comprobarRutNuevo() {
        if (! this.rutNuevo.trim()) { this.mensajeRut = ''; return }
        if (this.legacy) {
            this.buscar = this.rutNuevo
            await this.buscarPersona()
            const normalizar = (rut) => rut.replace(/[^0-9kK]/g, '').toUpperCase()
            const existente = this.opciones.find((persona) => normalizar(persona.rut) === normalizar(this.rutNuevo))
            this.mensajeRut = existente ? 'Este RUT ya existe. Seleccione la persona en la lista de personas registradas.' : 'Si el RUT es válido y no existe, complete los datos de la persona nueva.'
            return
        }
        const rut = this.rutNuevo.trim()
        const solicitudActual = ++this.solicitudRut
        const url = new URL(@js(route('reemplazos.personas')), window.location.origin)
        url.searchParams.set('rut', rut)
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } })
            if (! response.ok) throw new Error('No fue posible comprobar el RUT.')
            const personas = await response.json()
            if (solicitudActual !== this.solicitudRut || this.modo !== 'nuevo') return
            this.mensajeRut = personas.length ? 'Este RUT ya existe. Seleccione la persona en la lista de personas registradas.' : 'Si el RUT es válido y no existe, complete los datos de la persona nueva.'
        } catch (error) {
            if (solicitudActual === this.solicitudRut && this.modo === 'nuevo') this.mensajeRut = 'No fue posible comprobar el RUT.'
        }
    },
    iniciarNueva() {
        this.nuevoAbierto = ! this.nuevoAbierto
        if (this.nuevoAbierto) { this.personaId = ''; this.cambiarPersona() }
        else {
            this.rutNuevo = ''
            this.nombresNuevos = ''
            this.apellidoPaternoNuevo = ''
            this.apellidoMaternoNuevo = ''
            this.mensajeRut = ''
        }
    },
    cambiarPersona() {
        this.seleccionada = this.opcionesVisibles().find((persona) => persona.id === this.personaId) || null
        if (this.personaId) {
            this.nuevoAbierto = false
            this.rutNuevo = ''
            this.nombresNuevos = ''
            this.apellidoPaternoNuevo = ''
            this.apellidoMaternoNuevo = ''
            this.mensajeRut = ''
        }
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
}" x-init="seleccionada = opcionesBase.find((persona) => persona.id === personaId) || null; if (personaId && !seleccionada) personaId = ''; if (!legacy && modo === 'existente' && !personaId && buscar) buscarPersona()">
    <div class="flex items-center justify-between gap-3">
        <h3 class="font-semibold">{{ $legacy ? 'Reemplazante' : 'Datos del Reemplazante' }} {{ $legacy ? '' : '(opcional en V3)' }}</h3>
        @if($legacy)
            <button type="button" x-on:click="iniciarNueva()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:border-brand-primary/40 hover:text-brand-primary-dark" x-bind:aria-expanded="nuevoAbierto">
                <span aria-hidden="true">+</span> Nuevo reemplazante
            </button>
        @endif
    </div>
    @unless($legacy)
        <input type="hidden" name="modo_reemplazante" x-bind:value="modo">
        <div role="group" aria-label="Tipo de reemplazante" class="grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1">
            <button type="button" aria-controls="panel-reemplazante-existente" x-bind:aria-pressed="modo === 'existente'" x-on:click="$cambiarModoReemplazante($data, 'existente')" x-bind:class="modo === 'existente' ? 'bg-white text-brand-primary-dark shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="rounded-md px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">Persona existente</button>
            <button type="button" aria-controls="panel-reemplazante-nuevo" x-bind:aria-pressed="modo === 'nuevo'" x-on:click="$cambiarModoReemplazante($data, 'nuevo')" x-bind:class="modo === 'nuevo' ? 'bg-white text-brand-primary-dark shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="rounded-md px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">Nuevo reemplazante</button>
        </div>
    @endunless
    @unless($legacy)<div id="panel-reemplazante-existente" x-show="modo === 'existente'" x-cloak class="space-y-4">@endunless
    @if($legacy)
        <label class="block">Persona existente
            <select name="reemplazante_id" x-model="personaId" x-on:change="cambiarPersona()" class="mt-1 w-full rounded border-gray-300">
                <option value="">Pendiente / registrar nueva</option>
                @foreach($personas as $persona)<option value="{{ $persona->id }}">{{ $persona->nombre_completo }} · {{ $persona->rut }}</option>@endforeach
            </select>
            <x-input-error :messages="$errors->get('reemplazante_id')" class="mt-1" />
        </label>
    @else
        <input type="hidden" name="reemplazante_id" x-bind:value="personaId" x-bind:disabled="modo !== 'existente'" value="{{ old('reemplazante_id', $detalle?->reemplazante_id ?? '') }}">
        <div x-show="!personaId" x-cloak class="space-y-2">
            <label for="buscar-reemplazante" class="block text-sm font-medium text-gray-700">Buscar persona por RUT, nombres o apellidos</label>
            <input id="buscar-reemplazante" type="search" name="buscar_reemplazante" x-ref="buscarReemplazante" x-model="buscar" x-on:input="reiniciarBusqueda()" x-on:input.debounce.350ms="buscarPersona()" x-on:keydown.arrow-down.prevent="$refs.resultadosReemplazante?.querySelector('button')?.focus()" x-bind:disabled="modo !== 'existente'" maxlength="80" autocomplete="off" class="w-full rounded border-gray-300" placeholder="Escriba al menos 3 caracteres">
            <p class="text-xs text-gray-500">La búsqueda incluye personas registradas fuera de la unidad solicitante.</p>
            <span x-show="buscando" class="block text-xs text-gray-500">Buscando...</span>
            <span x-show="mensajeBusqueda && !sinCoincidencias" x-text="mensajeBusqueda" class="block text-xs text-gray-600"></span>
            <ul x-show="opciones.length && buscar.trim().length >= 3 && !buscando" x-cloak x-ref="resultadosReemplazante" class="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <template x-for="persona in opciones" x-bind:key="persona.id">
                    <li><button type="button" x-on:click="$seleccionarPersonaReemplazante($data, persona)" x-bind:disabled="persona.inactiva" class="w-full px-3 py-2 text-left hover:bg-brand-primary-soft focus:bg-brand-primary-soft focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50">
                        <span class="block text-sm font-semibold text-gray-900" x-text="persona.nombre"></span>
                        <span class="block text-xs text-gray-600" x-text="persona.rut"></span>
                        <span class="block text-xs text-gray-500" x-text="persona.inactiva ? 'RUT ya registrado; consulte su reactivación con Gestión de Personas.' : (persona.unidad_vigente || 'Sin unidad vigente')"></span>
                    </button></li>
                </template>
            </ul>
            <div x-show="sinCoincidencias" x-cloak class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                <p>No se encontraron personas activas para esta búsqueda.</p>
                <button type="button" x-on:click="$cambiarModoReemplazante($data, 'nuevo')" class="mt-2 font-semibold text-brand-primary-dark underline">Registrar nuevo reemplazante</button>
            </div>
        </div>
        <div x-show="personaId && seleccionada" x-cloak class="flex items-start justify-between gap-3 rounded-lg border border-brand-primary/20 bg-brand-primary-soft p-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Persona seleccionada</p>
                <p class="font-semibold text-gray-900" x-text="seleccionada?.nombre"></p>
                <p class="text-sm text-gray-700" x-text="seleccionada?.rut"></p>
                <p class="text-xs text-gray-600" x-text="seleccionada?.unidad_vigente || 'Sin unidad vigente'"></p>
            </div>
            <button type="button" x-on:click="$cambiarPersonaSeleccionada($data); $nextTick(() => $refs.buscarReemplazante.focus())" class="shrink-0 text-sm font-semibold text-brand-primary-dark underline">Cambiar persona</button>
        </div>
        <x-input-error :messages="$errors->get('reemplazante_id')" class="mt-1" />
    @endif
    @if($legacy)<div class="flex justify-center text-gray-300" aria-hidden="true">↓</div>@endif
    <div x-show="personaId && disponibles().length" class="rounded-lg border border-gray-200 bg-gray-50/60 px-3 py-2.5">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Antecedentes laborales conocidos</p>
            <p class="text-xs text-gray-500" x-text="`${disponibles().length} ${disponibles().length === 1 ? 'registro encontrado' : 'registros encontrados'}`"></p>
        </div>
        <div class="mt-2 divide-y divide-gray-200 border-t border-gray-200">
            <template x-for="item in disponibles()" x-bind:key="item.id">
                <article class="-mx-1 px-2 py-2.5 transition" x-bind:class="vinculoBase === item.id ? 'bg-brand-primary-soft/70' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900"><span x-text="item.profesion || 'Sin profesión informada'"></span><span class="font-normal text-gray-400"> · </span><span x-text="item.estamento || 'Sin estamento informado'"></span></p>
                            <p class="mt-0.5 truncate text-xs text-gray-600"><span x-text="item.calidad_contractual || 'Calidad no informada'"></span><span class="text-gray-400"> · </span><span x-text="item.cargo_funcion || 'Cargo no informado'"></span></p>
                            <p class="mt-0.5 truncate text-xs text-gray-500"><span x-text="item.unidad || 'Unidad no informada'"></span><span class="text-gray-400"> · </span><span x-text="item.vigencia"></span></p>
                        </div>
                        <button type="button" x-on:click="vinculoBase = item.id; copiarAntecedentes()" class="shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold" x-bind:class="vinculoBase === item.id ? 'border-brand-primary/30 bg-brand-primary-soft text-brand-primary-dark' : 'border-gray-300 bg-white text-brand-primary-dark hover:border-brand-primary/40'" x-text="vinculoBase === item.id ? 'Base seleccionada' : 'Usar como base'"></button>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <p x-show="personaId && ! disponibles().length" class="border-y border-gray-200 bg-gray-50/60 px-3 py-2.5 text-sm text-gray-600">{{ $legacy ? 'Sin antecedentes laborales registrados.' : 'No hay antecedentes laborales disponibles para copiar.' }}</p>
    @unless($legacy)</div><div id="panel-reemplazante-nuevo" x-show="modo === 'nuevo'" x-cloak>@endunless
    <div @if($legacy) x-show="nuevoAbierto" @endif class="border-y border-gray-200 bg-gray-50/60 px-3 py-3"><p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-600">Registrar nuevo reemplazante</p><div class="grid gap-3"><label class="text-sm">RUT<x-text-input name="nuevo_reemplazante_rut" x-model="rutNuevo" x-on:blur="comprobarRutNuevo()" x-bind:disabled="!legacy && modo !== 'nuevo'" value="{{ old('nuevo_reemplazante_rut') }}" placeholder="RUT"/><x-input-error :messages="$errors->get('nuevo_reemplazante_rut')" class="mt-1" /><span x-show="mensajeRut" x-text="mensajeRut" class="mt-1 block text-xs text-gray-600"></span></label><label class="text-sm">Nombres<x-text-input name="nuevo_reemplazante_nombres" x-model="nombresNuevos" x-bind:disabled="!legacy && modo !== 'nuevo'" value="{{ old('nuevo_reemplazante_nombres') }}" placeholder="Nombres"/><x-input-error :messages="$errors->get('nuevo_reemplazante_nombres')" class="mt-1" /></label><div class="grid grid-cols-2 gap-2"><label class="text-sm">Apellido paterno<x-text-input name="nuevo_reemplazante_apellido_paterno" x-model="apellidoPaternoNuevo" x-bind:disabled="!legacy && modo !== 'nuevo'" value="{{ old('nuevo_reemplazante_apellido_paterno') }}" placeholder="Apellido paterno"/></label><label class="text-sm">Apellido materno<x-text-input name="nuevo_reemplazante_apellido_materno" x-model="apellidoMaternoNuevo" x-bind:disabled="!legacy && modo !== 'nuevo'" value="{{ old('nuevo_reemplazante_apellido_materno') }}" placeholder="Apellido materno"/></label></div></div></div>
    @unless($legacy)</div>@endunless
    @if($legacy)<div class="flex justify-center text-gray-300" aria-hidden="true">↓</div>@endif
    @unless($legacy)
        <label class="block">Unidad donde desempeñará funciones
            <select name="unidad_destino_id" class="mt-1 w-full rounded border-gray-300" required>
                <option value="">Seleccione</option>
                @foreach($unidades as $unidad)
                    <option value="{{ $unidad->id }}" @selected((string) old('unidad_destino_id', $solicitudContrato?->unidad_destino_id ?? '') === (string) $unidad->id)>{{ $unidad->nombre }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('unidad_destino_id')" class="mt-1" />
        </label>
    @endunless
    <fieldset class="grid gap-3 sm:grid-cols-2">
        <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-700">Antecedentes para este reemplazo</legend>
        <label class="text-sm">Estamento *<select name="reemplazante_estamento_id" x-model="estamentoId" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($estamentos as $estamento)<option value="{{ $estamento->id }}">{{ $estamento->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_estamento_id')" class="mt-1" /></label>
        <label class="text-sm">Profesión<select name="reemplazante_profesion_id" x-model="profesionId" class="mt-1 w-full rounded border-gray-300"><option value="">No corresponde / pendiente</option>@foreach($profesiones as $profesion)<option value="{{ $profesion->id }}">{{ $profesion->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_profesion_id')" class="mt-1" /></label>
        <label class="text-sm">Calidad contractual *<select name="reemplazante_calidad_contractual_id" x-model="calidadId" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente</option>@foreach($calidades as $calidad)<option value="{{ $calidad->id }}">{{ $calidad->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_calidad_contractual_id')" class="mt-1" /></label>
        <label class="text-sm">Cargo / función *<input name="reemplazante_cargo_funcion" x-model="cargo" maxlength="200" class="mt-1 w-full rounded border-gray-300"><x-input-error :messages="$errors->get('reemplazante_cargo_funcion')" class="mt-1" /></label>
        <p class="text-xs text-gray-500 sm:col-span-2">Estos datos son una propuesta de esta solicitud. Guardar el borrador no crea ni modifica Dotación. {{ $legacy ? 'Son obligatorios para enviar.' : 'Complételos si propone una persona; puede enviar V3 sin candidato.' }}</p>
    </fieldset>
    <h4 class="font-medium">Período efectivo del reemplazante</h4><div class="grid grid-cols-2 gap-3"><label>Desde<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_desde" value="{{ old('fecha_reemplazante_desde',$detalle?->fecha_reemplazante_desde?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_desde')" class="mt-1" /></label><label>Hasta<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_hasta" value="{{ old('fecha_reemplazante_hasta',$detalle?->fecha_reemplazante_hasta?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_hasta')" class="mt-1" /></label></div>@if($detalle && $detalle->diasFuncionario() > 0)<div class="rounded bg-slate-50 p-3 text-sm"><p>Período funcionario: {{ $detalle->diasFuncionario() }} días calendario</p><p>Período reemplazante: {{ $detalle->diasReemplazante() }} días calendario</p><p>Días sin cobertura: {{ $detalle->diasSinCobertura() }}</p>@if($detalle->coberturaParcial())<p class="mt-2 rounded bg-amber-50 p-2 text-amber-800">El reemplazante cubrirá {{ $detalle->diasReemplazante() }} de los {{ $detalle->diasFuncionario() }} días del período solicitado. Quedarán {{ $detalle->diasSinCobertura() }} días sin cobertura.</p>@endif</div>@endif
</section>
<section class="rounded-xl bg-white p-6 shadow lg:col-span-2">
    <h3 class="mb-3 font-semibold">Documentos adjuntos</h3>
    @if(!$tramite)
        <p class="text-sm text-gray-500">Guarde primero el borrador para habilitar adjuntos.</p>
    @else
        @can('tramites.adjuntos.cargar')
            <div class="grid gap-3 border-b border-gray-200 pb-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,20rem)_auto] sm:items-end">
                <label class="text-sm">Archivo<input form="adjunto-form" type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required class="mt-1 block w-full text-sm"></label>
                <label class="text-sm">Tipo documental<select form="adjunto-form" name="tipo_documento_id" class="mt-1 w-full rounded border-gray-300"><option value="">Sin tipo documental</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                <x-secondary-button type="submit" form="adjunto-form">Adjuntar</x-secondary-button>
            </div>
            @error('archivo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        @endcan
        <div class="mt-4 space-y-3">
            @forelse($tramite->adjuntos as $adjunto)
                <div class="rounded-lg border border-gray-200 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><p class="font-medium text-gray-900">{{ $adjunto->original_name }}</p><p class="text-xs text-gray-500">{{ $adjunto->tipoDocumento?->nombre ?? 'Sin tipo documental' }} · versión {{ $adjunto->version }} · {{ $adjunto->status }}</p></div>
                        @can('tramites.adjuntos.descargar')<a href="{{ route('reemplazos.adjuntos.download', [$tramite, $adjunto]) }}" class="text-sm font-semibold text-brand-primary-dark">Descargar</a>@endcan
                    </div>
                    @if($adjunto->status === 'ACTIVO' && $adjunto->documentoGenerado === null)
                        <div class="mt-3 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-3">
                            @can('tramites.adjuntos.cargar')
                                <label class="text-xs">Nueva versión<input form="adjunto-version-{{ $adjunto->id }}" type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required class="mt-1 block text-sm"></label>
                                <x-secondary-button type="submit" form="adjunto-version-{{ $adjunto->id }}">Versionar</x-secondary-button>
                            @endcan
                            @can('tramites.adjuntos.anular')<x-secondary-button type="submit" form="adjunto-anular-{{ $adjunto->id }}">Anular adjunto</x-secondary-button>@endcan
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin documentos adjuntos.</p>
            @endforelse
        </div>
    @endif
</section>
</form>
@if($tramite && !$legacy && $tramite->estadoTramite?->codigo === 'BORRADOR')
    <section class="mt-5 rounded-xl border border-gray-200 bg-white p-6 shadow">
        <h3 class="font-semibold">Respaldo transitorio</h3>
        <p class="mt-1 text-sm text-gray-600">Guarde primero el funcionario y sus fechas. Registrar el respaldo no lo compromete ni reserva a una persona.</p>
        <div class="mt-4 space-y-2 text-sm">
            @forelse($respaldos as $respaldo)
                <p class="rounded border border-gray-200 bg-gray-50 p-3">{{ $respaldo->public_id }} · {{ $respaldo->versionActual?->fecha_desde?->format('d/m/Y') }} al {{ $respaldo->versionActual?->fecha_hasta?->format('d/m/Y') }} · {{ $respaldo->versionActual?->motivo }}</p>
            @empty
                <p class="text-amber-800">Todavía no hay respaldo registrado. Se exige uno válido para enviar.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('reemplazos.respaldo-transitorio.store', $tramite) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="text-sm">Motivo del respaldo<input name="motivo" required maxlength="2000" value="{{ old('motivo') }}" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Referencia externa opcional<input name="referencia_externa" maxlength="200" value="{{ old('referencia_externa') }}" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Desde<input type="date" name="fecha_desde" required value="{{ old('fecha_desde', $detalle?->fecha_funcionario_desde?->toDateString()) }}" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Hasta<input type="date" name="fecha_hasta" required value="{{ old('fecha_hasta', $detalle?->fecha_funcionario_hasta?->toDateString()) }}" class="mt-1 w-full rounded border-gray-300"></label>
            <div class="sm:col-span-2"><x-primary-button>Registrar respaldo</x-primary-button></div>
        </form>
    </section>
@endif
@if($tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION')@php($devolucion=$tramite->historial()->where('action_code','DEVOLVER_PARA_CORRECCION')->latest('occurred_at')->first())@if($devolucion)<section class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><h3 class="font-semibold">Última observación de devolución</h3><p class="mt-2">{{ $devolucion->observation }}</p></section>@endif @endif</div></div></x-app-layout>
