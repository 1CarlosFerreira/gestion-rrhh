<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estamento extends Model
{
    protected $fillable = ['codigo', 'nombre', 'activo'];

    public function vinculosDotacion(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
