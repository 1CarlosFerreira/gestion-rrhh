<?php

namespace App\Services\Dotacion;

use App\Enums\OrigenVinculoDotacion;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Profesion;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\EstructuraOrganizacionalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DotacionService
{
    public function __construct(private readonly EstructuraOrganizacionalService $estructura) {}

    public function crear(array $datos, User $actor): PersonaUnidadVinculo
    {
        return DB::transaction(function () use ($datos, $actor): PersonaUnidadVinculo {
            $this->validarReferencias($datos);
            $datos = $this->normalizar($datos);
            $this->validarPeriodo($datos);
            $this->validarSolapamiento($datos);

            return PersonaUnidadVinculo::query()->create([...$datos, 'created_by' => $actor->id]);
        });
    }

    public function crearDesdeDocumentoFirmado(Tramite $tramite, array $datos, User $actor): PersonaUnidadVinculo
    {
        return DB::transaction(function () use ($tramite, $datos, $actor): PersonaUnidadVinculo {
            $existente = PersonaUnidadVinculo::query()->where('origen_tramite_id', $tramite->id)->lockForUpdate()->first();
            if ($existente !== null) {
                return $existente;
            }

            return $this->crear([...$datos, 'origen' => OrigenVinculoDotacion::DOCUMENTO_FIRMADO->value, 'origen_tramite_id' => $tramite->id], $actor);
        });
    }

    public function actualizar(PersonaUnidadVinculo $vinculo, array $datos, User $actor): PersonaUnidadVinculo
    {
        return DB::transaction(function () use ($vinculo, $datos, $actor): PersonaUnidadVinculo {
            $vinculo = PersonaUnidadVinculo::query()->lockForUpdate()->findOrFail($vinculo->id);
            $identidad = ['persona_id', 'unidad_organizacional_id', 'calidad_contractual_id', 'cargo_funcion', 'estamento_id', 'profesion_id', 'grado_eus', 'origen', 'origen_tramite_id', 'vigente_desde'];
            if ($vinculo->vigente_desde->lte(today()) && $this->cambia($vinculo, $datos, $identidad)) {
                throw ValidationException::withMessages(['vinculo' => 'Un vínculo iniciado conserva sus datos laborales; ciérrelo y cree uno nuevo.']);
            }
            $combinados = [...$vinculo->getAttributes(), ...$datos];
            $this->validarReferencias($combinados, $vinculo->vigente_desde->lte(today()));
            $combinados = $this->normalizar($combinados);
            $this->validarPeriodo($combinados);
            $this->validarSolapamiento($combinados, $vinculo->id);
            $vinculo->update([...$datos, 'cargo_funcion_normalizado' => $combinados['cargo_funcion_normalizado'], 'updated_by' => $actor->id]);

            return $vinculo->refresh();
        });
    }

    public function cerrar(PersonaUnidadVinculo $vinculo, string $fecha, User $actor): PersonaUnidadVinculo
    {
        return DB::transaction(function () use ($vinculo, $fecha, $actor): PersonaUnidadVinculo {
            $vinculo = PersonaUnidadVinculo::query()->lockForUpdate()->findOrFail($vinculo->id);

            if ($vinculo->vigente_hasta !== null) {
                throw ValidationException::withMessages(['vigente_hasta' => 'El vínculo laboral ya se encuentra cerrado.']);
            }

            return $this->actualizar($vinculo, ['vigente_hasta' => $fecha], $actor);
        });
    }

    public function dotacionVigente(UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha, bool $incluirDescendientes = false): Collection
    {
        $ids = collect([$unidad->id]);
        if ($incluirDescendientes) {
            $ids = $ids->merge($this->estructura->descendientes($unidad)->where('activo', true)->pluck('id'));
        }

        return PersonaUnidadVinculo::query()->with(['persona.user', 'unidad', 'estamento', 'profesion', 'calidadContractual'])->whereIn('unidad_organizacional_id', $ids)->vigentesEn($fecha)->get();
    }

    public function historicosPersona(Persona $persona): Collection
    {
        return $persona->vinculosDotacion()->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')->get();
    }

    public function historialUnidad(UnidadOrganizacional $unidad): Collection
    {
        return $unidad->vinculosDotacion()->with(['persona', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')->get();
    }

    public function proximasIncorporaciones(?UnidadOrganizacional $unidad = null, string|\DateTimeInterface|null $desde = null): Collection
    {
        $desde ??= today();

        return PersonaUnidadVinculo::query()->with(['persona', 'unidad'])->whereDate('vigente_desde', '>', $desde)->when($unidad, fn (Builder $q) => $q->where('unidad_organizacional_id', $unidad->id))->orderBy('vigente_desde')->get();
    }

    public function pertenece(Persona $persona, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        return $persona->vinculosDotacion()->where('unidad_organizacional_id', $unidad->id)->vigentesEn($fecha)->exists();
    }

    public function unidadesVigentes(Persona $persona, string|\DateTimeInterface $fecha): Collection
    {
        return UnidadOrganizacional::query()->where('activo', true)->whereHas('vinculosDotacion', fn (Builder $q) => $q->where('persona_id', $persona->id)->vigentesEn($fecha))->get();
    }

    private function validarReferencias(array $datos, bool $edicion = false): void
    {
        $persona = Persona::query()->findOrFail($datos['persona_id']);
        $unidad = UnidadOrganizacional::query()->findOrFail($datos['unidad_organizacional_id']);
        $estamento = Estamento::query()->findOrFail($datos['estamento_id']);
        $calidad = CalidadContractual::query()->findOrFail($datos['calidad_contractual_id']);
        $profesion = empty($datos['profesion_id']) ? null : Profesion::query()->findOrFail($datos['profesion_id']);
        if (! $edicion && (! $persona->active || ! $unidad->activo || ! $estamento->activo || ! $calidad->activo || ($profesion && ! $profesion->activo))) {
            throw ValidationException::withMessages(['catalogos' => 'La persona, unidad y catálogos deben estar activos.']);
        }
        if ($profesion?->estamento_id !== null && $profesion->estamento_id !== $estamento->id) {
            throw ValidationException::withMessages(['profesion_id' => 'La profesión no corresponde al estamento seleccionado.']);
        }
        $origen = $datos['origen'] instanceof OrigenVinculoDotacion ? $datos['origen'] : OrigenVinculoDotacion::from($datos['origen']);
        if ($origen === OrigenVinculoDotacion::DOCUMENTO_FIRMADO && empty($datos['origen_tramite_id'])) {
            throw ValidationException::withMessages(['origen_tramite_id' => 'El documento firmado requiere un trámite autorizante.']);
        }
        if ($origen !== OrigenVinculoDotacion::DOCUMENTO_FIRMADO && ! empty($datos['origen_tramite_id'])) {
            throw ValidationException::withMessages(['origen_tramite_id' => 'Solo el origen documento firmado admite trámite autorizante.']);
        }
    }

    private function normalizar(array $datos): array
    {
        $datos['cargo_funcion'] = preg_replace('/\s+/u', ' ', trim($datos['cargo_funcion']));
        $datos['cargo_funcion_normalizado'] = mb_strtolower($datos['cargo_funcion']);

        return $datos;
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
        $existe = PersonaUnidadVinculo::query()->where('persona_id', $datos['persona_id'])->where('unidad_organizacional_id', $datos['unidad_organizacional_id'])->where('calidad_contractual_id', $datos['calidad_contractual_id'])->where('cargo_funcion_normalizado', $datos['cargo_funcion_normalizado'])->when($ignorarId, fn (Builder $q) => $q->whereKeyNot($ignorarId))->whereDate('vigente_desde', '<=', $fin)->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $inicio))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['vigente_desde' => 'El periodo se superpone con un vínculo laboral equivalente.']);
        }
    }

    private function cambia(PersonaUnidadVinculo $vinculo, array $datos, array $campos): bool
    {
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }
            $actual = $vinculo->{$campo};
            $actual = $actual instanceof \BackedEnum ? $actual->value : ($actual instanceof \DateTimeInterface ? $actual->format('Y-m-d') : $actual);
            if ((string) ($datos[$campo] ?? '') !== (string) ($actual ?? '')) {
                return true;
            }
        }

        return false;
    }
}
