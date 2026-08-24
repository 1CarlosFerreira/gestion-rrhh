<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaUnidadVinculo extends Model
{
    public const ESTADOS = ['ACTIVO', 'EN_TRAMITACION', 'INACTIVO'];

    public const ESTADOS_OPERATIVOS = ['ACTIVO', 'EN_TRAMITACION'];

    protected $fillable = ['persona_id', 'unidad_servicio_id', 'estamento_id', 'profesion_id', 'cargo_texto', 'start_date', 'end_date', 'status'];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_servicio_id');
    }

    public function estamento(): BelongsTo
    {
        return $this->belongsTo(Estamento::class);
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(Profesion::class);
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
