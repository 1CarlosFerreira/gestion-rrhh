<?php

namespace App\Models;

use App\Enums\TipoResponsabilidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

class UnidadResponsable extends Model
{
    protected $table = 'unidad_responsables';

    protected $guarded = ['id'];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_organizacional_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
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

    public function estaVigenteEn(string|\DateTimeInterface $fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();

        return $this->vigente_desde->lte($fecha) && ($this->vigente_hasta === null || $this->vigente_hasta->gte($fecha));
    }

    public function habilitadoParaActuar(string|\DateTimeInterface $fecha, bool $requiereAprobacion = false): bool
    {
        return $this->estaVigenteEn($fecha) && (! $requiereAprobacion || $this->puede_aprobar) && $this->persona->user?->active === true;
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Las responsabilidades institucionales no se eliminan físicamente.'));
    }

    protected function casts(): array
    {
        return ['tipo' => TipoResponsabilidad::class, 'vigente_desde' => 'date', 'vigente_hasta' => 'date', 'puede_aprobar' => 'boolean'];
    }
}
