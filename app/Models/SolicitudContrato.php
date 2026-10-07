<?php

namespace App\Models;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudContrato extends Model
{
    protected $fillable = [
        'tramite_id',
        'modalidad',
        'unidad_solicitante_id',
        'unidad_origen_id',
        'unidad_destino_id',
    ];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function unidadSolicitante(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_solicitante_id');
    }

    public function autoridad(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'autoridad_persona_id');
    }

    public function autoridadResponsabilidad(): BelongsTo
    {
        return $this->belongsTo(UnidadResponsable::class, 'autoridad_responsabilidad_id');
    }

    public function unidadOrigen(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_origen_id');
    }

    public function unidadDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizacional::class, 'unidad_destino_id');
    }

    public function getRegistradorAttribute(): ?User
    {
        return $this->tramite?->creador;
    }

    protected function casts(): array
    {
        return [
            'modalidad' => ModalidadSolicitudContrato::class,
            'autoridad_contexto' => ContextoAutoridadInstitucional::class,
            'autoridad_resuelta_at' => 'datetime',
        ];
    }
}
