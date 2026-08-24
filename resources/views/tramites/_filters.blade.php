<form method="GET" class="grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-3 lg:grid-cols-4">
    <select name="tipo_tramite_id" class="rounded border-gray-300"><option value="">Todos los tipos</option>@foreach($tipos as $item)<option value="{{ $item->id }}" @selected((string) request('tipo_tramite_id') === (string) $item->id)>{{ $item->nombre }}</option>@endforeach</select>
    <select name="estado_tramite_id" class="rounded border-gray-300"><option value="">Todos los estados</option>@foreach($estados as $item)<option value="{{ $item->id }}" @selected((string) request('estado_tramite_id') === (string) $item->id)>{{ $item->nombre }} ({{ $item->tipoTramite?->nombre }})</option>@endforeach</select>
    <select name="unidad_servicio_id" class="rounded border-gray-300"><option value="">Todas las unidades</option>@foreach($unidades as $item)<option value="{{ $item->id }}" @selected((string) request('unidad_servicio_id') === (string) $item->id)>{{ $item->nombre }}</option>@endforeach</select>
    @if($withSearch ?? false)<x-text-input name="codigo" :value="request('codigo')" placeholder="Código"/><x-text-input type="date" name="desde" :value="request('desde')"/><x-text-input type="date" name="hasta" :value="request('hasta')"/>@endif
    <x-primary-button>Filtrar</x-primary-button>
</form>
