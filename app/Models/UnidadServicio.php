<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadServicio extends Model
{
    protected $table = 'unidades_servicios';

    protected $fillable = ['nombre', 'activo'];

    public function vinculosPersonas(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class);
    }

    public function usuariosHabilitados(): BelongsToMany
    {
        $today = now()->toDateString();

        return $this->belongsToMany(User::class, 'user_unidades')
            ->withPivot(['id', 'active', 'valid_from', 'valid_to'])
            ->withTimestamps()
            ->where('user_unidades.active', true)
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
