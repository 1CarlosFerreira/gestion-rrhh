<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CalidadContractual extends Model
{
    protected $table = 'calidades_contractuales';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo', 'orden'];

    public function vinculos(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $calidad): void {
            if ($calidad->vinculos()->exists()) {
                throw new LogicException('No se puede eliminar una calidad contractual utilizada.');
            }
        });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }
}
