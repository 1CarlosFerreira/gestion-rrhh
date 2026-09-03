<?php

namespace App\Services\Accesos;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\EstructuraOrganizacionalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccesoOperativoService
{
    public function __construct(private readonly EstructuraOrganizacionalService $estructura) {}

    public function crear(array $datos, User $actor): UserUnidadAcceso
    {
        return DB::transaction(function () use ($datos, $actor): UserUnidadAcceso {
            $user = User::query()->lockForUpdate()->findOrFail($datos['user_id']);
            $unidad = UnidadOrganizacional::query()->findOrFail($datos['unidad_organizacional_id']);
            if (! $user->active || $user->persona_id === null) {
                throw ValidationException::withMessages(['user_id' => 'El usuario debe estar activo y asociado a una Persona.']);
            }
            if (! $unidad->activo) {
                throw ValidationException::withMessages(['unidad_organizacional_id' => 'La unidad debe estar activa.']);
            }
            $this->validarPeriodo($datos);
            $this->validarSolapamiento($datos);

            return UserUnidadAcceso::query()->create([...$datos, 'created_by' => $actor->id]);
        });
    }

    public function actualizar(UserUnidadAcceso $acceso, array $datos, User $actor): UserUnidadAcceso
    {
        return DB::transaction(function () use ($acceso, $datos, $actor): UserUnidadAcceso {
            $acceso = UserUnidadAcceso::query()->lockForUpdate()->findOrFail($acceso->id);
            $cambioIdentidad = collect(['user_id', 'unidad_organizacional_id', 'alcance', 'vigente_desde'])->contains(function (string $campo) use ($datos, $acceso): bool {
                if (! array_key_exists($campo, $datos)) {
                    return false;
                }
                $actual = $acceso->{$campo};
                $actual = $actual instanceof \BackedEnum ? $actual->value : ($actual instanceof \DateTimeInterface ? $actual->format('Y-m-d') : $actual);

                return (string) $datos[$campo] !== (string) $actual;
            });
            if ($acceso->vigente_desde->lte(today()) && $cambioIdentidad) {
                throw ValidationException::withMessages(['acceso' => 'Un acceso ya iniciado conserva usuario, unidad, alcance y fecha inicial.']);
            }
            $combinados = [...$acceso->getAttributes(), ...$datos];
            $this->validarPeriodo($combinados);
            $this->validarSolapamiento($combinados, $acceso->id);
            $acceso->update([...$datos, 'updated_by' => $actor->id]);

            return $acceso->refresh();
        });
    }

    public function cerrarAcceso(UserUnidadAcceso $acceso, string $fecha, User $actor): UserUnidadAcceso
    {
        return $this->actualizar($acceso, ['vigente_hasta' => $fecha], $actor);
    }

    public function accesosVigentes(User $user, string|\DateTimeInterface $fecha): Collection
    {
        if (! $user->active) {
            return collect();
        }

        return $user->accesosOperativos()->with('unidad')->vigentesEn($fecha)->get();
    }

    public function historicos(User $user): Collection
    {
        return $user->accesosOperativos()->with(['unidad', 'creadoPor', 'actualizadoPor'])->orderByDesc('vigente_desde')->get();
    }

    public function tieneAcceso(User $user, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        if (! $user->active || ! $unidad->fresh()->activo) {
            return false;
        }
        foreach ($this->accesosVigentes($user, $fecha) as $acceso) {
            if (! $acceso->unidad->fresh()->activo) {
                continue;
            }
            if ($acceso->unidad_organizacional_id === $unidad->id || ($acceso->alcance === AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES && $this->estructura->contiene($acceso->unidad->fresh(), $unidad->fresh()))) {
                return true;
            }
        }

        return false;
    }

    public function unidadesAccesibles(User $user, string|\DateTimeInterface $fecha): Collection
    {
        if (! $user->active) {
            return collect();
        }
        $unidades = collect();
        foreach ($this->accesosVigentes($user, $fecha) as $acceso) {
            $base = $acceso->unidad->fresh();
            if (! $base->activo) {
                continue;
            }
            $unidades->put($base->id, $base);
            if ($acceso->alcance === AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES) {
                foreach ($this->estructura->descendientes($base)->where('activo', true) as $unidad) {
                    $unidades->put($unidad->id, $unidad);
                }
            }
        }

        return $unidades->values();
    }

    public function tienePermisoYAcceso(User $user, string $permiso, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha, bool $alcanceGlobal = false): bool
    {
        return $user->active && $user->can($permiso) && ($alcanceGlobal || $this->tieneAcceso($user, $unidad, $fecha));
    }

    private function validarPeriodo(array $datos): void
    {
        if (! empty($datos['vigente_hasta']) && CarbonImmutable::parse($datos['vigente_hasta'])->lt(CarbonImmutable::parse($datos['vigente_desde']))) {
            throw ValidationException::withMessages(['vigente_hasta' => 'La fecha final no puede ser anterior al inicio.']);
        }
    }

    private function validarSolapamiento(array $datos, ?int $ignorarId = null): void
    {
        $inicio = CarbonImmutable::parse($datos['vigente_desde'])->toDateString();
        $fin = empty($datos['vigente_hasta']) ? '9999-12-31' : CarbonImmutable::parse($datos['vigente_hasta'])->toDateString();
        $alcance = $datos['alcance'] instanceof AlcanceAccesoOperativo ? $datos['alcance']->value : $datos['alcance'];
        $existe = UserUnidadAcceso::query()->where('user_id', $datos['user_id'])->where('unidad_organizacional_id', $datos['unidad_organizacional_id'])->where('alcance', $alcance)->when($ignorarId, fn (Builder $q) => $q->whereKeyNot($ignorarId))->whereDate('vigente_desde', '<=', $fin)->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $inicio))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['vigente_desde' => 'El periodo se superpone con otro acceso equivalente.']);
        }
    }
}
