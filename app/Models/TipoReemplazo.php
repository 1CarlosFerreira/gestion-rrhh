<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoReemplazo extends Model
{
    protected $table = 'tipos_reemplazo';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
