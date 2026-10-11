@if($tramite->solicitudContrato)
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Anulación administrativa</h2>
        @if(session('status'))<p class="mt-2 text-sm text-emerald-800">{{ session('status') }}</p>@endif
        <x-input-error :messages="$errors->get('motivo')" />
        <x-input-error :messages="$errors->get('solicitud')" />
        @can('anular-reemplazo', $tramite)
            <form method="POST" action="{{ route('reemplazos.annul', $tramite) }}" class="mt-3 space-y-3" onsubmit="return confirm('¿Confirma la anulación definitiva de esta solicitud y la liberación de sus compromisos?')">
                @csrf
                <label class="block text-sm">Motivo obligatorio<textarea name="motivo" required maxlength="2000" class="mt-1 block w-full rounded border-gray-300">{{ old('motivo') }}</textarea></label>
                <button class="rounded bg-red-700 px-4 py-2 text-white">Anular solicitud</button>
            </form>
        @elsecan('solicitar-anulacion-reemplazo', $tramite)
            <form method="POST" action="{{ route('reemplazos.request-annulment', $tramite) }}" class="mt-3 space-y-3" onsubmit="return confirm('¿Confirma el envío de esta petición a Gestión de Personas?')">
                @csrf
                <p class="text-sm">Gestión de Personas resolverá la petición. Los compromisos permanecen vigentes.</p>
                <label class="block text-sm">Motivo obligatorio<textarea name="motivo" required maxlength="2000" class="mt-1 block w-full rounded border-gray-300">{{ old('motivo') }}</textarea></label>
                <button class="rounded bg-gray-800 px-4 py-2 text-white">Solicitar anulación</button>
            </form>
        @endcan
        @foreach($tramite->historial as $evento)
            @if(in_array($evento->action_code, ['SOLICITAR_ANULACION', 'ANULAR_SOLICITUD'], true))
                <div class="mt-4 border-t pt-3 text-sm">
                    <p class="font-medium">{{ $evento->action_code === 'ANULAR_SOLICITUD' ? 'Anulación ejecutada' : 'Petición de anulación' }} · {{ $evento->usuario?->name }} · {{ $evento->occurred_at->format('d/m/Y H:i') }}</p>
                    <p class="whitespace-pre-line">{{ $evento->observation }}</p>
                    @if($evento->action_code === 'SOLICITAR_ANULACION')<p>{{ $tramite->estadoTramite->codigo === 'ANULADA' ? 'Resuelta mediante anulación del expediente' : 'Pendiente de resolución' }}</p>@endif
                </div>
            @endif
        @endforeach
    </section>
@endif
