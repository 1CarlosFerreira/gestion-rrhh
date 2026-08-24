<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'active', 'activo'];

    public function getActivoAttribute(): bool
    {
        return $this->active;
    }

    public function setActivoAttribute(bool $value): void
    {
        $this->attributes['active'] = $value;
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(TramiteAdjunto::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
