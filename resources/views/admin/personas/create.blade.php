@extends('layouts.admin')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Crear Persona</h1>

    <form method="POST" action="{{ route('admin.personas.store') }}">
        @csrf

        <div class="mb-4">
            <label for="rut" class="block font-medium mb-1">RUT</label>
            <input type="text" name="rut" id="rut" value="{{ old('rut') }}" class="border rounded px-2 py-1 w-full" required>
            @error('rut')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="nombres" class="block font-medium mb-1">Nombres</label>
            <input type="text" name="nombres" id="nombres" value="{{ old('nombres') }}" class="border rounded px-2 py-1 w-full" required>
            @error('nombres')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="apellido_paterno" class="block font-medium mb-1">Apellido Paterno</label>
            <input type="text" name="apellido_paterno" id="apellido_paterno" value="{{ old('apellido_paterno') }}" class="border rounded px-2 py-1 w-full" required>
            @error('apellido_paterno')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="apellido_materno" class="block font-medium mb-1">Apellido Materno</label>
            <input type="text" name="apellido_materno" id="apellido_materno" value="{{ old('apellido_materno') }}" class="border rounded px-2 py-1 w-full">
            @error('apellido_materno')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="active" class="inline-flex items-center">
                <input type="checkbox" name="active" id="active" value="1" {{ old('active', true) ? 'checked' : '' }} class="form-checkbox">
                <span class="ml-2">Activo</span>
            </label>
            @error('active')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Crear</button>
        <a href="{{ route('admin.personas.index') }}" class="ml-4 text-gray-600 hover:underline">Cancelar</a>
    </form>
</div>
@endsection