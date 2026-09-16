<?php

namespace App\Models;

use App\Enums\EstadoVinculoDotacion;
use App\Enums\OrigenVinculoDotacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PersonaUnidadVinculo extends Model
{
    protected $guarded = ['id'];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_organizacional_id');
    }

    public function estamento(): BelongsTo
    {
        return $this->belongsTo(Estamento::class);
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(Profesion::class);
    }

    public function calidadContractual(): BelongsTo
    {
        return $this->belongsTo(CalidadContractual::class);
    }

    public function tramiteOrigen(): BelongsTo
    {
        return $this->belongsTo(Tramite::class, 'origen_tramite_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVigentesEn(Builder $query, string|\DateTimeInterface $fecha): Builder
    {
        $fecha = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : $fecha;

        return $query->whereDate('vigente_desde', '<=', $fecha)->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha));
    }

    public function estadoEn(string|\DateTimeInterface $fecha): EstadoVinculoDotacion
    {
        $fecha = CarbonImmutable::parse($fecha)->startOfDay();
        if ($this->vigente_desde->gt($fecha)) {
            return EstadoVinculoDotacion::FUTURO;
        }
        if ($this->vigente_hasta !== null && $this->vigente_hasta->lt($fecha)) {
            return EstadoVinculoDotacion::FINALIZADO;
        }

        return EstadoVinculoDotacion::VIGENTE;
    }

    public function esGeneradoPorTramite(): bool
    {
        return $this->origen === OrigenVinculoDotacion::DOCUMENTO_FIRMADO
            && $this->origen_tramite_id !== null;
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Los vínculos de dotación no se eliminan físicamente.'));
    }

    protected function casts(): array
    {
        return ['origen' => OrigenVinculoDotacion::class, 'vigente_desde' => 'date', 'vigente_hasta' => 'date', 'grado_eus' => 'integer'];
    }
}
