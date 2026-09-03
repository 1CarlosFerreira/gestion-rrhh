<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class UnidadOrganizacional extends Model
{
    protected $table = 'unidades_organizacionales';

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('nombre');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('activo', true);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoUnidadOrganizacional::class, 'tipo_unidad_organizacional_id');
    }

    public function responsables(): HasMany
    {
        return $this->hasMany(UnidadResponsable::class);
    }

    public function accesosOperativos(): HasMany
    {
        return $this->hasMany(UserUnidadAcceso::class);
    }

    public function vinculosDotacion(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class);
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeRaices(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('orden')->orderBy('nombre');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $unidad): void {
            if ($unidad->children()->exists()) {
                throw new LogicException('No se puede eliminar una unidad con hijos.');
            }
        });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'participa_en_aprobacion' => 'boolean'];
    }
}
