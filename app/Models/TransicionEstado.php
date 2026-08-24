<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransicionEstado extends Model
{
    protected $table = 'transiciones_estado';

    protected $fillable = [
        'tipo_tramite_id', 'estado_origen_id', 'estado_destino_id', 'codigo_accion',
        'permiso_requerido', 'requiere_observacion', 'activo',
    ];

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    public function estadoOrigen(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class, 'estado_origen_id');
    }

    public function estadoDestino(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class, 'estado_destino_id');
    }

    protected function casts(): array
    {
        return ['requiere_observacion' => 'boolean', 'activo' => 'boolean'];
    }
}
