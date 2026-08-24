@csrf
@if(isset($persona)) @method('PUT') @endif
<div class="grid gap-4 sm:grid-cols-2">
    <div><x-input-label for="rut" value="RUT"/><x-text-input id="rut" name="rut" class="mt-1 block w-full" :value="old('rut', $persona->rut ?? '')" required/><x-input-error :messages="$errors->get('rut')" class="mt-2"/></div>
    <div><x-input-label for="nombres" value="Nombres"/><x-text-input id="nombres" name="nombres" class="mt-1 block w-full" :value="old('nombres', $persona->nombres ?? '')" required/><x-input-error :messages="$errors->get('nombres')" class="mt-2"/></div>
    <div><x-input-label for="apellido_paterno" value="Apellido paterno"/><x-text-input id="apellido_paterno" name="apellido_paterno" class="mt-1 block w-full" :value="old('apellido_paterno', $persona->apellido_paterno ?? '')"/></div>
    <div><x-input-label for="apellido_materno" value="Apellido materno"/><x-text-input id="apellido_materno" name="apellido_materno" class="mt-1 block w-full" :value="old('apellido_materno', $persona->apellido_materno ?? '')"/></div>
</div>
<div class="mt-5 flex gap-3"><x-primary-button>Guardar</x-primary-button><a class="rounded border px-4 py-2 text-sm" href="{{ route('personas.index') }}">Cancelar</a></div>
