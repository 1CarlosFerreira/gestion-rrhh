<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estamento extends Model
{
    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
