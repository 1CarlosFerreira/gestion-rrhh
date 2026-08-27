@php($tramite = $tramite ?? null)
@csrf
@if(isset($tramite)) @method('PUT') @endif
<div><x-input-label for="unidad_servicio_id" value="1. Unidad / Servicio"/><select id="unidad_servicio_id" name="unidad_servicio_id" required class="mt-1 block w-full rounded border-gray-300"><option value="">Seleccione</option>@foreach($unidades as $unidad)<option value="{{ $unidad->id }}" @selected(old('unidad_servicio_id', $selectedUnidadId) == $unidad->id)>{{ $unidad->nombre }}</option>@endforeach</select><x-input-error :messages="$errors->get('unidad_servicio_id')" class="mt-2"/></div>
<div class="grid gap-4 md:grid-cols-2"><div><x-input-label for="month" value="2. Mes"/><select id="month" name="month" required class="mt-1 block w-full rounded border-gray-300">@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected(old('month', request('month', $tramite?->horasExtra?->month ?? now()->month)) == $month)>{{ \Carbon\CarbonImmutable::create(2000, $month)->locale('es')->translatedFormat('F') }}</option>@endforeach</select><x-input-error :messages="$errors->get('month')" class="mt-2"/></div><div><x-input-label for="year" value="Año"/><input id="year" name="year" type="number" min="2000" max="2100" required value="{{ old('year', request('year', $tramite?->horasExtra?->year ?? now()->year)) }}" class="mt-1 block w-full rounded border-gray-300"><x-input-error :messages="$errors->get('year')" class="mt-2"/></div></div>
<fieldset><legend class="font-medium">3. Funcionarios de {{ $selectedUnidad?->nombre ?? 'la unidad seleccionada' }}</legend><p class="mb-2 text-sm text-gray-600">Seleccione uno o más funcionarios con vínculo activo y vigente.</p>@if($personas->isNotEmpty())<label class="mb-2 block text-sm">Buscar por RUT o nombre<input id="buscar_funcionario" type="search" class="mt-1 block w-full rounded border-gray-300" placeholder="Escriba para filtrar esta lista"></label>@endif<div id="lista_funcionarios" class="max-h-96 space-y-2 overflow-y-auto rounded border p-3">@forelse($personas as $persona) @php($vinculo = $persona->vinculos->first())<label data-funcionario="{{ \Illuminate\Support\Str::lower($persona->nombre_completo.' '.$persona->rut) }}" class="flex items-start gap-3 rounded p-2 hover:bg-gray-50"><input type="checkbox" name="persona_ids[]" value="{{ $persona->id }}" class="mt-1 rounded" @checked(in_array($persona->id, old('persona_ids', $tramite ? $tramite->horasExtra->funcionarios->pluck('persona_id')->all() : [])))><span><strong>{{ $persona->nombre_completo }}</strong> · {{ $persona->rut }}<small class="block text-gray-600">{{ $vinculo?->estamento?->nombre ?? 'Sin estamento' }} · {{ $vinculo?->profesion?->nombre ?? 'Sin profesión' }} · {{ $vinculo?->cargo_texto ?? 'Sin cargo' }}</small></span></label>@empty<p class="text-sm text-gray-600">La unidad seleccionada no tiene funcionarios con vínculo activo y vigente.</p>@endforelse</div><x-input-error :messages="$errors->get('persona_ids')" class="mt-2"/><x-input-error :messages="$errors->get('persona_ids.*')" class="mt-2"/></fieldset>
@if($errors->any())<div class="rounded bg-red-50 p-3 text-sm text-red-800"><p class="font-medium">No fue posible guardar el borrador. Revise los campos indicados.</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<x-primary-button>{{ isset($tramite) ? 'Guardar borrador' : 'Crear borrador' }}</x-primary-button>
<script>
    document.getElementById('unidad_servicio_id')?.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('unidad_servicio_id', this.value);
        url.searchParams.set('month', document.getElementById('month').value);
        url.searchParams.set('year', document.getElementById('year').value);
        window.location.assign(url.toString());
    });
    document.getElementById('buscar_funcionario')?.addEventListener('input', function () {
        const term = this.value.toLocaleLowerCase('es').trim();
        document.querySelectorAll('#lista_funcionarios [data-funcionario]').forEach((item) => {
            item.classList.toggle('hidden', !item.dataset.funcionario.includes(term));
        });
    });
</script>
