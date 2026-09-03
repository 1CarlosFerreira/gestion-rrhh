<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesion extends Model
{
    protected $table = 'profesiones';

    protected $fillable = ['estamento_id', 'codigo', 'nombre', 'activo'];

    public function estamento(): BelongsTo
    {
        return $this->belongsTo(Estamento::class);
    }

    public function vinculosDotacion(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
