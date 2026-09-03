<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class TipoReemplazo extends Model
{
    protected $table = 'tipos_reemplazo';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo', 'orden'];

    public function reemplazos(): HasMany
    {
        return $this->hasMany(TramiteReemplazo::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $tipo): void {
            if ($tipo->reemplazos()->exists()) {
                throw new LogicException('No se puede eliminar un tipo de reemplazo utilizado.');
            }
        });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }
}
