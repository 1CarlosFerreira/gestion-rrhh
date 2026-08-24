<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profesion extends Model
{
    protected $table = 'profesiones';

    protected $fillable = ['estamento_id', 'codigo', 'nombre', 'activo'];

    public function estamento(): BelongsTo
    {
        return $this->belongsTo(Estamento::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
