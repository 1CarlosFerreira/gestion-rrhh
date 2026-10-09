<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReservaPersonaPeriodo extends Model
{
    protected $table = 'reserva_persona_periodos';

    protected $fillable = ['afectacion_id', 'persona_id', 'fecha_desde', 'fecha_hasta', 'reservado_at', 'reservado_por', 'liberado_at', 'liberado_por', 'motivo_liberacion'];

    public function afectacion(): BelongsTo
    {
        return $this->belongsTo(RespaldoAfectacion::class, 'afectacion_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function reservadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reservado_por');
    }

    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por');
    }

    public function getEstadoAttribute(): string
    {
        return $this->liberado_at === null ? 'VIGENTE' : 'LIBERADA';
    }

    protected static function booted(): void
    {
        static::saving(function (self $reserva): void {
            if ($reserva->fecha_desde === null || $reserva->fecha_hasta === null || $reserva->fecha_desde->gt($reserva->fecha_hasta)) {
                throw new LogicException('El período reservado debe tener fechas ordenadas.');
            }
            $liberada = $reserva->liberado_at !== null;
            if ($liberada !== ($reserva->liberado_por !== null) || $liberada !== filled($reserva->motivo_liberacion)) {
                throw new LogicException('La liberación requiere fecha, responsable y motivo.');
            }
            if ($liberada && $reserva->liberado_at->lt($reserva->reservado_at)) {
                throw new LogicException('La liberación no puede anteceder a la reserva.');
            }
        });
        static::updating(function (self $reserva): void {
            if ($reserva->getOriginal('liberado_at') !== null || array_diff(array_keys($reserva->getDirty()), ['liberado_at', 'liberado_por', 'motivo_liberacion'])) {
                throw new LogicException('Una reserva histórica solo puede liberarse una vez.');
            }
        });
        static::deleting(fn () => throw new LogicException('Las reservas no se eliminan físicamente.'));
    }

    protected function casts(): array
    {
        return ['fecha_desde' => 'date', 'fecha_hasta' => 'date', 'reservado_at' => 'datetime', 'liberado_at' => 'datetime'];
    }
}
