<?php

namespace App\Support\Tramites;

use Illuminate\Http\Request;

class ResolverRetornoTramite
{
    private const ADMIN_FILTERS = [
        'buscar',
        'tipo',
        'estado',
        'unidad',
        'creador',
        'fecha_desde',
        'fecha_hasta',
        'orden',
        'per_page',
        'page',
    ];

    private const REVIEW_FILTERS = [
        'buscar',
        'estado',
        'unidad_id',
        'pestana',
        'activos_page',
        'finalizados_page',
    ];

    public function resolve(Request $request, ?string $defaultOrigin = null): array
    {
        $origin = $request->string('from')->toString() ?: $defaultOrigin;

        return match ($origin) {
            'admin_tramites' => [
                'href' => route('admin.tramites.index', $this->safeQuery($request, self::ADMIN_FILTERS)),
                'text' => 'Volver a Todos los trámites',
            ],
            'revision_reemplazos' => [
                'href' => route('gestion-personas.reemplazos.index', $this->safeQuery($request, self::REVIEW_FILTERS)),
                'text' => 'Volver a Revisión de reemplazos',
            ],
            default => [
                'href' => route('dashboard'),
                'text' => 'Volver al Inicio',
            ],
        };
    }

    private function safeQuery(Request $request, array $allowed): array
    {
        $query = $request->input('return', []);

        if (! is_array($query)) {
            return [];
        }

        return collect($query)
            ->only($allowed)
            ->filter(fn ($value) => is_scalar($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->all();
    }
}
