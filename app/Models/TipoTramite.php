<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoTramite extends Model
{
    protected $table = 'tipos_tramite';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    public function estados(): HasMany
    {
        return $this->hasMany(EstadoTramite::class)->orderBy('orden');
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
