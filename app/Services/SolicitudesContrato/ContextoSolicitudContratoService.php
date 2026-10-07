<?php

namespace App\Services\SolicitudesContrato;

use App\Enums\ContextoAutoridadInstitucional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ContextoSolicitudContratoService
{
    public function __construct(
        private readonly AccesoOperativoService $accesos,
        private readonly AutoridadInstitucionalResolver $autoridad,
    ) {}

    public function unidadesAutorizadas(User $actor, string|\DateTimeInterface $fecha): Collection
    {
        if (! $actor->active || ! $actor->can('reemplazos.crear')) {
            return collect();
        }

        return $this->accesos->unidadesAccesibles($actor, $fecha);
    }

    public function prepararTransitoria(array $datos, User $actor, string|\DateTimeInterface $fecha): array
    {
        if (! $actor->active || ! $actor->can('reemplazos.crear')) {
            throw new AuthorizationException('No está autorizado para gestionar solicitudes de contrato transitorias.');
        }

        $campos = ['unidad_solicitante_id', 'unidad_origen_id', 'unidad_destino_id'];
        $ids = collect($campos)->mapWithKeys(fn (string $campo): array => [$campo => (int) ($datos[$campo] ?? 0)]);
        if ($ids->contains(fn (int $id): bool => $id <= 0)) {
            throw ValidationException::withMessages(
                $ids->filter(fn (int $id): bool => $id <= 0)->mapWithKeys(fn (int $id, string $campo): array => [$campo => 'La unidad es obligatoria.'])->all(),
            );
        }

        $unidades = UnidadOrganizacional::query()
            ->whereIn('id', $ids->values()->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $campo => $id) {
            $unidad = $unidades->get($id);
            if (! $unidad instanceof UnidadOrganizacional || ! $unidad->activo) {
                throw ValidationException::withMessages([$campo => 'La unidad debe existir y estar activa.']);
            }
            if (! $this->accesos->tieneAcceso($actor, $unidad, $fecha)) {
                throw new AuthorizationException("No está autorizado para utilizar la unidad indicada en {$campo}.");
            }
        }

        $solicitante = $unidades->get($ids['unidad_solicitante_id']);
        $responsabilidad = $this->autoridad->resolver(
            $solicitante,
            $fecha,
            ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO,
        );

        return [
            'unidad_solicitante' => $solicitante,
            'unidad_origen' => $unidades->get($ids['unidad_origen_id']),
            'unidad_destino' => $unidades->get($ids['unidad_destino_id']),
            'autoridad_responsabilidad' => $responsabilidad,
            'autoridad_contexto' => ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO,
        ];
    }
}
