<?php

namespace App\Models;

use App\Support\Rut\Rut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $fillable = ['rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'active'];

    public function vinculos(): HasMany
    {
        return $this->hasMany(PersonaUnidadVinculo::class)->latest('start_date');
    }

    public function vinculosOperativos(): HasMany
    {
        return $this->vinculos()->whereIn('status', PersonaUnidadVinculo::ESTADOS_OPERATIVOS);
    }

    public function scopeBuscar(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $normalizedRut = Rut::normalize($term);

        return $query->where(function (Builder $query) use ($term, $normalizedRut): void {
            $query->where('rut', 'like', $normalizedRut.'%')
                ->orWhere('nombres', 'like', '%'.$term.'%')
                ->orWhere('apellido_paterno', 'like', '%'.$term.'%')
                ->orWhere('apellido_materno', 'like', '%'.$term.'%');
        });
    }

    public function getNombreCompletoAttribute(): string
    {
        return implode(' ', array_filter([$this->nombres, $this->apellido_paterno, $this->apellido_materno]));
    }

    public function setRutAttribute(?string $value): void
    {
        $this->attributes['rut'] = Rut::normalize($value);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
