<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">Continuar configuración</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6">
            <x-admin-process-steps current="acceso" />

            <section class="rounded-xl border border-green-200 bg-green-50 px-5 py-4">
                <h2 class="font-semibold text-green-900">✓ Incorporación completada</h2>
                <div class="mt-2 text-sm text-green-950">
                    <p class="font-medium">{{ $vinculo->persona->nombre_completo }}</p>
                    <p>{{ $vinculo->unidad->nombre }}</p>
                    <p class="mt-1 text-green-800">{{ $vinculo->cargo_funcion }} · {{ $vinculo->calidadContractual->nombre }}</p>
                    <p class="text-green-800">{{ $responsabilidad?->tipo?->etiqueta() ?? 'Funcionario' }} · desde {{ $vinculo->vigente_desde->format('d/m/Y') }}</p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Acceso al sistema</h2>

                @if ($user === null)
                    <p class="mt-3 text-sm text-gray-600">Esta persona todavía no tiene una cuenta de usuario.</p>
                    <div class="mt-5 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                        <a href="{{ route('admin.personas.show', $vinculo->persona) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">Finalizar sin configurar</a>
                        <a href="{{ route('admin.usuarios.create-for-persona', ['persona' => $vinculo->persona, 'continuar_perfil' => 1]) }}" class="inline-flex items-center justify-center rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primary-dark">Configurar acceso al sistema →</a>
                    </div>
                @else
                    <p class="mt-3 text-sm font-semibold text-green-700">✓ Usuario existente</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Correo</dt><dd class="mt-1 text-gray-900">{{ $user->email }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ $user->active ? 'Activo' : 'Inactivo' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-gray-500">Roles actuales</dt><dd class="mt-1 text-gray-900">{{ $user->roles->pluck('name')->join(', ') ?: 'Sin roles asignados' }}</dd></div>
                    </dl>

                    <div class="mt-6 grid gap-5 border-t border-gray-100 pt-5 md:grid-cols-2">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Alcance por responsabilidades vigentes</h3>
                            <ul class="mt-2 space-y-2 text-sm text-gray-700">
                                @forelse ($alcancesResponsabilidad as $alcance)
                                    <li>{{ $alcance->unidad->nombre }} · {{ $alcance->tipo->etiqueta() }}</li>
                                @empty
                                    <li class="text-gray-500">Sin alcance vigente por responsabilidad.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Accesos operativos vigentes</h3>
                            <ul class="mt-2 space-y-2 text-sm text-gray-700">
                                @forelse ($accesosOperativos as $acceso)
                                    <li>{{ $acceso->unidad->nombre }} · {{ $acceso->alcance->etiqueta() }} @if ($acceso->cubre_nueva_unidad)<span class="font-semibold text-green-700">· Cubre esta unidad</span>@endif</li>
                                @empty
                                    <li class="text-gray-500">Sin accesos operativos vigentes.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                        <a href="{{ route('admin.personas.show', $vinculo->persona) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">Finalizar</a>
                        <a href="{{ route('admin.usuarios.perfil-acceso.edit', $user) }}" class="inline-flex items-center justify-center rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primary-dark">Revisar acceso →</a>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
