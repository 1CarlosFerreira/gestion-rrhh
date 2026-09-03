<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoUnidadOrganizacional extends Model
{
    protected $table = 'tipos_unidad_organizacional';

    protected $guarded = ['id'];

    public function unidades(): HasMany
    {
        return $this->hasMany(UnidadOrganizacional::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
