<?php

namespace App\Models;

use App\Enums\ModalidadSolicitudContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class RespaldoAfectacion extends Model
{
    protected $table = 'respaldo_afectaciones';

    protected $fillable = ['respaldo_id', 'respaldo_version_id', 'solicitud_contrato_id', 'comprometido_at', 'comprometido_por', 'liberado_at', 'liberado_por', 'motivo_liberacion'];

    public function respaldo(): BelongsTo
    {
        return $this->belongsTo(RespaldoTransitorio::class, 'respaldo_id');
    }

    public function versionRespaldo(): BelongsTo
    {
        return $this->belongsTo(RespaldoTransitorioVersion::class, 'respaldo_version_id');
    }

    public function solicitudContrato(): BelongsTo
    {
        return $this->belongsTo(SolicitudContrato::class);
    }

    public function comprometidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprometido_por');
    }

    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaPersonaPeriodo::class, 'afectacion_id')->orderBy('reservado_at')->orderBy('id');
    }

    public function getEstadoAttribute(): string
    {
        return $this->liberado_at === null ? 'VIGENTE' : 'LIBERADA';
    }

    protected static function booted(): void
    {
        static::creating(function (self $afectacion): void {
            if (SolicitudContrato::query()->find($afectacion->solicitud_contrato_id)?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
                throw new LogicException('Una afectación transitoria requiere una solicitud transitoria.');
            }
        });
        static::saving(function (self $afectacion): void {
            $liberada = $afectacion->liberado_at !== null;
            if ($liberada !== ($afectacion->liberado_por !== null) || $liberada !== filled($afectacion->motivo_liberacion)) {
                throw new LogicException('La liberación requiere fecha, responsable y motivo.');
            }
            if ($liberada && $afectacion->liberado_at->lt($afectacion->comprometido_at)) {
                throw new LogicException('La liberación no puede anteceder al compromiso.');
            }
        });
        static::updating(function (self $afectacion): void {
            if ($afectacion->getOriginal('liberado_at') !== null || array_diff(array_keys($afectacion->getDirty()), ['liberado_at', 'liberado_por', 'motivo_liberacion'])) {
                throw new LogicException('Una afectación histórica solo puede liberarse una vez.');
            }
        });
        static::deleting(fn () => throw new LogicException('Las afectaciones no se eliminan físicamente.'));
    }

    protected function casts(): array
    {
        return ['comprometido_at' => 'datetime', 'liberado_at' => 'datetime'];
    }
}
