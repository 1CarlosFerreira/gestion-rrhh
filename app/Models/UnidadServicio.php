<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadServicio extends Model
{
    protected $table = 'unidades_servicios';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
