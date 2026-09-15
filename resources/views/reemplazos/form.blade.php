<x-app-layout><x-slot name="header"><div class="flex items-center justify-between"><div><h2 class="text-xl font-semibold">{{ $tramite ? $tramite->codigo : 'Nueva Solicitud de Reemplazo' }}</h2><p class="text-sm text-gray-500">Estado: {{ $tramite?->estadoTramite?->nombre ?? 'Borrador' }} @if($tramite) · {{ $tramite->unidadOrganizacional->nombre }} @endif</p></div><div class="text-right"><div class="flex gap-3"><button type="submit" form="reemplazo-form" class="rounded bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">{{ $tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Guardar cambios' : 'Guardar borrador' }}</button>@if($tramite)<button type="submit" form="reemplazo-form" formaction="{{ route('reemplazos.send',$tramite) }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $tramite->estadoTramite->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'Reenviar a Gestión de Personas' : 'Enviar a Gestión de Personas' }}</button>@endif</div>@if($tramite)<p class="mt-1 text-xs text-gray-500">Al enviar se guardan los cambios actuales y luego se validan los antecedentes.</p>@endif</div></div></x-slot>
<div class="py-10"><div class="mx-auto max-w-7xl px-4">@if(session('status'))<div class="mb-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mb-4 rounded bg-red-50 p-3 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @if($tramite)<form id="adjunto-form" method="POST" enctype="multipart/form-data" action="{{ route('reemplazos.adjuntos.store',$tramite) }}">@csrf</form>@endif
<form id="reemplazo-form" method="POST" action="{{ $tramite ? route('reemplazos.update',$tramite) : route('reemplazos.store') }}" class="grid gap-5 lg:grid-cols-2">@csrf @if($tramite)@method('PUT')@endif
@php
    $unidadInicial = (string) old('unidad_organizacional_id', $tramite?->unidad_organizacional_id ?? '');
    $funcionarioInicial = (string) old('funcionario_id', $detalle?->funcionario_id ?? '');
    $fechaFuncionarioInicial = old('fecha_funcionario_desde', $detalle?->fecha_funcionario_desde?->toDateString() ?? '');
    $funcionariosIniciales = $funcionarios->map(fn ($persona) => ['id' => (string) $persona->id, 'nombre' => $persona->nombre_completo, 'rut' => $persona->rut])->values();
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
<section class="space-y-4 rounded-xl bg-white p-6 shadow"><h3 class="font-semibold">Reemplazante único</h3><label class="block">Persona existente<select name="reemplazante_id" class="mt-1 w-full rounded border-gray-300"><option value="">Pendiente / registrar nueva</option>@foreach($personas as $persona)<option value="{{ $persona->id }}" @selected(old('reemplazante_id',$detalle?->reemplazante_id)==$persona->id)>{{ $persona->nombre_completo }} · {{ $persona->rut }}</option>@endforeach</select><x-input-error :messages="$errors->get('reemplazante_id')" class="mt-1" /></label><details class="rounded border p-3"><summary class="cursor-pointer text-sm text-indigo-700">+ Registrar nuevo reemplazante</summary><div class="mt-3 grid gap-3"><x-text-input name="nuevo_reemplazante_rut" value="{{ old('nuevo_reemplazante_rut') }}" placeholder="RUT"/><x-text-input name="nuevo_reemplazante_nombres" value="{{ old('nuevo_reemplazante_nombres') }}" placeholder="Nombres"/><div class="grid grid-cols-2 gap-2"><x-text-input name="nuevo_reemplazante_apellido_paterno" value="{{ old('nuevo_reemplazante_apellido_paterno') }}" placeholder="Apellido paterno"/><x-text-input name="nuevo_reemplazante_apellido_materno" value="{{ old('nuevo_reemplazante_apellido_materno') }}" placeholder="Apellido materno"/></div></div></details><h4 class="font-medium">Período efectivo del reemplazante</h4><div class="grid grid-cols-2 gap-3"><label>Desde<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_desde" value="{{ old('fecha_reemplazante_desde',$detalle?->fecha_reemplazante_desde?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_desde')" class="mt-1" /></label><label>Hasta<x-text-input type="date" class="mt-1 w-full" name="fecha_reemplazante_hasta" value="{{ old('fecha_reemplazante_hasta',$detalle?->fecha_reemplazante_hasta?->toDateString()) }}"/><x-input-error :messages="$errors->get('fecha_reemplazante_hasta')" class="mt-1" /></label></div>@if($detalle && $detalle->diasFuncionario() > 0)<div class="rounded bg-slate-50 p-3 text-sm"><p>Período funcionario: {{ $detalle->diasFuncionario() }} días calendario</p><p>Período reemplazante: {{ $detalle->diasReemplazante() }} días calendario</p><p>Días sin cobertura: {{ $detalle->diasSinCobertura() }}</p>@if($detalle->coberturaParcial())<p class="mt-2 rounded bg-amber-50 p-2 text-amber-800">El reemplazante cubrirá {{ $detalle->diasReemplazante() }} de los {{ $detalle->diasFuncionario() }} días del período solicitado. Quedarán {{ $detalle->diasSinCobertura() }} días sin cobertura.</p>@endif</div>@endif</section>
<section class="rounded-xl bg-white p-6 shadow"><h3 class="mb-3 font-semibold">Justificación</h3><textarea name="justificacion" rows="8" class="w-full rounded border-gray-300" placeholder="Puede completarse progresivamente">{{ old('justificacion',$detalle?->justificacion) }}</textarea><x-input-error :messages="$errors->get('justificacion')" class="mt-1" /></section>
<section class="rounded-xl bg-white p-6 shadow"><h3 class="mb-3 font-semibold">Documentos</h3>@if(!$tramite)<p class="text-sm text-gray-500">Guarde primero el borrador para habilitar adjuntos.</p>@else @can('tramites.adjuntos.cargar')<div class="space-y-3"><input form="adjunto-form" type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required>@error('archivo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<select form="adjunto-form" name="tipo_documento_id" class="w-full rounded border-gray-300"><option value="">Sin tipo documental</option>@foreach($tiposDocumento as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select><x-secondary-button type="submit" form="adjunto-form">Adjuntar</x-secondary-button></div>@endcan<div class="mt-4 space-y-2">@forelse($tramite->adjuntos as $adjunto)<p class="text-sm">{{ $adjunto->original_name }} · v{{ $adjunto->version }} · {{ $adjunto->status }}</p>@empty<p class="text-sm text-gray-500">Sin documentos adjuntos.</p>@endforelse</div>@endif</section>
</form>@if($tramite?->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION')@php($devolucion=$tramite->historial()->where('action_code','DEVOLVER_PARA_CORRECCION')->latest('occurred_at')->first())@if($devolucion)<section class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><h3 class="font-semibold">Última observación de devolución</h3><p class="mt-2">{{ $devolucion->observation }}</p></section>@endif @endif</div></div></x-app-layout>
