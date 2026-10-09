<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RespaldoTransitorioVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'respaldo_transitorio_versiones';

    protected $fillable = ['version', 'funcionario_origen_id', 'unidad_origen_id', 'motivo', 'fecha_desde', 'fecha_hasta', 'referencia_externa', 'registrado_por', 'motivo_rectificacion'];

    public function respaldo(): BelongsTo
    {
        return $this->belongsTo(RespaldoTransitorio::class, 'respaldo_id');
    }

    public function funcionarioOrigen(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'funcionario_origen_id');
    }

    public function unidadOrigen(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_origen_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if ($version->version === null || $version->version < 1) {
                throw new LogicException('La versión del respaldo debe comenzar en uno.');
            }
            if ($version->fecha_desde === null || $version->fecha_hasta === null || $version->fecha_desde->gt($version->fecha_hasta)) {
                throw new LogicException('El período del respaldo debe tener fechas ordenadas.');
            }
            if ($version->version > 1 && blank($version->motivo_rectificacion)) {
                throw new LogicException('Una rectificación debe indicar su motivo.');
            }
        });
        static::updating(fn () => throw new LogicException('Las versiones del respaldo son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las versiones del respaldo son inmutables.'));
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'fecha_desde' => 'date', 'fecha_hasta' => 'date'];
    }
}
