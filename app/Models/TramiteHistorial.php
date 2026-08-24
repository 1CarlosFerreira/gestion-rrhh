<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteHistorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tramite_historial';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial de trámites es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial de trámites es inmutable.'));
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estadoOrigen(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class, 'from_estado_id');
    }

    public function estadoDestino(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class, 'to_estado_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
