<?php

namespace App\Models;

use App\Enums\AlcanceAccesoOperativo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class UserUnidadAcceso extends Model
{
    protected $table = 'user_unidad_accesos';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_organizacional_id');
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

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Los accesos operativos no se eliminan físicamente.'));
    }

    protected function casts(): array
    {
        return ['alcance' => AlcanceAccesoOperativo::class, 'vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }
}
