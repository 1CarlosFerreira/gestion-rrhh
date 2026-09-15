@can('tramites.ver_propios')
    <section aria-labelledby="mis-tramites-title">
        <div class="mb-3">
            <h3 id="mis-tramites-title" class="text-lg font-semibold text-gray-900">Mis trámites en curso</h3>
            <p class="mt-0.5 text-sm text-gray-500">Tus cinco trámites abiertos actualizados más recientemente.</p>
        </div>
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-gray-600">
                    <tr><th class="p-3">Código</th><th class="p-3">Tipo</th><th class="p-3">Unidad</th><th class="p-3">Funcionario / asunto</th><th class="p-3">Estado</th><th class="p-3">Última actualización</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    @forelse($misTramites as $tramite)
                        @php($puedeContinuar = $tramite->tipoTramite?->codigo === 'REEMPLAZO' && in_array($tramite->estadoTramite?->codigo, ['BORRADOR', 'DEVUELTA_PARA_CORRECCION'], true))
                        @php($estadoClase = match($tramite->estadoTramite?->codigo) {'BORRADOR' => 'bg-slate-100 text-slate-700', 'DEVUELTA_PARA_CORRECCION' => 'bg-amber-100 text-amber-800', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION' => 'bg-blue-100 text-blue-800', 'LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO' => 'bg-violet-100 text-violet-800', default => 'bg-gray-100 text-gray-700'})
                        <tr class="border-t transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono font-medium text-gray-900">{{ $tramite->codigo }}</td>
                            <td class="px-3 py-2.5">{{ $tramite->tipoTramite?->nombre ?? '—' }}</td>
                            <td class="max-w-56 px-3 py-2.5"><span class="block truncate">{{ $tramite->unidadOrganizacional?->nombre ?? '—' }}</span></td>
                            <td class="max-w-56 px-3 py-2.5"><span class="block truncate">{{ $tramite->reemplazo?->funcionario?->nombre_completo ?? '—' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2.5"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $estadoClase }}">{{ $tramite->estadoTramite?->nombre ?? '—' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-gray-600">{{ $tramite->updated_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2.5 text-right">
                                @if($tramite->tipoTramite?->codigo === 'REEMPLAZO')
                                    <a class="inline-flex items-center whitespace-nowrap rounded-md px-3 py-1.5 font-semibold text-indigo-700 transition hover:bg-indigo-50" href="{{ $puedeContinuar ? route('reemplazos.edit', $tramite) : route('reemplazos.show', $tramite) }}">{{ $puedeContinuar ? 'Continuar' : 'Ver' }} <span class="ml-1" aria-hidden="true">→</span></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-slate-500">No tienes trámites en curso.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endcan
