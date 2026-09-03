<?php

namespace App\Services\Responsabilidades;

use App\Enums\TipoResponsabilidad;
use App\Models\Persona;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Services\EstructuraOrganizacionalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponsabilidadInstitucionalService
{
    public function __construct(private readonly EstructuraOrganizacionalService $estructura) {}

    public function crear(array $datos, User $actor): UnidadResponsable
    {
        return DB::transaction(function () use ($datos, $actor): UnidadResponsable {
            $unidad = UnidadOrganizacional::query()->lockForUpdate()->findOrFail($datos['unidad_organizacional_id']);
            if (! $unidad->activo) {
                throw ValidationException::withMessages(['unidad_organizacional_id' => 'La unidad debe estar activa.']);
            }
            $this->validarPeriodo($datos);
            $this->validarSolapamiento($datos);

            return UnidadResponsable::query()->create([...$datos, 'created_by' => $actor->id]);
        });
    }

    public function actualizar(UnidadResponsable $responsabilidad, array $datos, User $actor): UnidadResponsable
    {
        return DB::transaction(function () use ($responsabilidad, $datos, $actor): UnidadResponsable {
            $responsabilidad = UnidadResponsable::query()->lockForUpdate()->findOrFail($responsabilidad->id);
            $identidadCambiada = collect(['unidad_organizacional_id', 'persona_id', 'tipo', 'vigente_desde'])->contains(function (string $campo) use ($datos, $responsabilidad): bool {
                if (! array_key_exists($campo, $datos)) {
                    return false;
                }
                $actual = $responsabilidad->{$campo};
                $actual = $actual instanceof \BackedEnum ? $actual->value : ($actual instanceof \DateTimeInterface ? $actual->format('Y-m-d') : $actual);

                return (string) $datos[$campo] !== (string) $actual;
            });
            if ($responsabilidad->vigente_desde->lte(today()) && $identidadCambiada) {
                throw ValidationException::withMessages(['responsabilidad' => 'Una responsabilidad ya iniciada conserva persona, unidad, tipo y fecha inicial.']);
            }
            $combinados = [...$responsabilidad->getAttributes(), ...$datos];
            $this->validarPeriodo($combinados);
            $this->validarSolapamiento($combinados, $responsabilidad->id);
            $responsabilidad->update([...$datos, 'updated_by' => $actor->id]);

            return $responsabilidad->refresh();
        });
    }

    public function cerrar(UnidadResponsable $responsabilidad, string $fecha, User $actor): UnidadResponsable
    {
        return $this->actualizar($responsabilidad, ['vigente_hasta' => $fecha], $actor);
    }

    public function titularVigente(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): ?UnidadResponsable
    {
        return $this->vigente($unidad, TipoResponsabilidad::TITULAR, $fecha);
    }

    public function subroganteVigente(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): ?UnidadResponsable
    {
        return $this->vigente($unidad, TipoResponsabilidad::SUBROGANTE, $fecha);
    }

    public function responsableVigente(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): ?UnidadResponsable
    {
        return $this->subroganteVigente($unidad, $fecha) ?? $this->titularVigente($unidad, $fecha);
    }

    public function responsableHabilitado(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha, bool $requiereAprobacion = false, ?Persona $excluir = null): ?UnidadResponsable
    {
        foreach ([TipoResponsabilidad::SUBROGANTE, TipoResponsabilidad::TITULAR] as $tipo) {
            $responsable = $this->vigente($unidad, $tipo, $fecha, $excluir);
            if ($responsable?->habilitadoParaActuar($fecha, $requiereAprobacion)) {
                return $responsable;
            }
        }

        return null;
    }

    public function primerResponsableSuperior(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha, ?Persona $excluir = null): ?UnidadResponsable
    {
        foreach ($this->estructura->ancestros($unidad)->reverse() as $ancestro) {
            $ancestro = $ancestro->fresh();
            if ($ancestro->activo && $ancestro->participa_en_aprobacion && ($responsable = $this->responsableHabilitado($ancestro, $fecha, true, $excluir))) {
                return $responsable;
            }
        }

        return null;
    }

    public function historicas(UnidadOrganizacional $unidad): Collection
    {
        return $unidad->responsables()->with(['persona.user', 'creadoPor', 'actualizadoPor'])->orderByDesc('vigente_desde')->get();
    }

    public function vigentesDePersona(Persona $persona, string|\DateTimeInterface $fecha): Collection
    {
        return $persona->responsabilidades()->vigentesEn($fecha)->with('unidad')->get();
    }

    public function esResponsable(Persona $persona, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha, bool $requiereAprobacion = false): bool
    {
        return $unidad->responsables()->whereBelongsTo($persona)->vigentesEn($fecha)->when($requiereAprobacion, fn (Builder $q) => $q->where('puede_aprobar', true))->exists();
    }

    private function vigente(UnidadOrganizacional $unidad, TipoResponsabilidad $tipo, string|\DateTimeInterface $fecha, ?Persona $excluir = null): ?UnidadResponsable
    {
        return $unidad->responsables()->with('persona.user')->where('tipo', $tipo->value)->vigentesEn($fecha)->when($excluir, fn (Builder $q) => $q->where('persona_id', '!=', $excluir->id))->first();
    }

    private function validarPeriodo(array $datos): void
    {
        if (! empty($datos['vigente_hasta']) && CarbonImmutable::parse($datos['vigente_hasta'])->lt(CarbonImmutable::parse($datos['vigente_desde']))) {
            throw ValidationException::withMessages(['vigente_hasta' => 'La fecha de término no puede ser anterior al inicio.']);
        }
    }

    private function validarSolapamiento(array $datos, ?int $ignorarId = null): void
    {
        $inicio = CarbonImmutable::parse($datos['vigente_desde'])->toDateString();
        $fin = empty($datos['vigente_hasta']) ? '9999-12-31' : CarbonImmutable::parse($datos['vigente_hasta'])->toDateString();
        $existe = UnidadResponsable::query()->where('unidad_organizacional_id', $datos['unidad_organizacional_id'])->where('tipo', $datos['tipo'] instanceof TipoResponsabilidad ? $datos['tipo']->value : $datos['tipo'])->when($ignorarId, fn (Builder $q) => $q->whereKeyNot($ignorarId))->whereDate('vigente_desde', '<=', $fin)->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $inicio))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['vigente_desde' => 'El periodo se superpone con otra responsabilidad del mismo tipo.']);
        }
    }
}
