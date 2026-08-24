<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadoTramite extends Model
{
    protected $table = 'estados_tramite';

    protected $fillable = ['tipo_tramite_id', 'codigo', 'nombre', 'orden', 'activo'];

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
