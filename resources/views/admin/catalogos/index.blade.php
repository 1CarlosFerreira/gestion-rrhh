<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Catálogos</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif
        @foreach ($catalogos as $clave => $registros)
            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <h3 class="border-b px-5 py-4 text-lg font-semibold">{{ str($clave)->replace('-', ' ')->headline() }} <span class="text-sm font-normal text-gray-500">({{ $registros->count() }})</span></h3>
                <div class="divide-y">
                    @foreach ($registros as $registro)
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <div><span>{{ $registro->nombre }}</span>@if(isset($registro->codigo))<code class="ml-2 text-xs text-gray-500">{{ $registro->codigo }}</code>@endif</div>
                            <form method="POST" action="{{ route('admin.catalogos.activo', [$clave, $registro->id]) }}">@csrf @method('PATCH')
                                <button class="rounded px-3 py-1 text-sm {{ $registro->activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}" type="submit">{{ $registro->activo ? 'Activo' : 'Inactivo' }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div></div>
</x-app-layout>
