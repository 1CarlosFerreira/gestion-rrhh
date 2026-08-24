<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradoEus extends Model
{
    protected $table = 'grados_eus';

    protected $fillable = ['grado', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
