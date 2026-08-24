<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClasificacionArea extends Model
{
    protected $table = 'clasificaciones_area';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
