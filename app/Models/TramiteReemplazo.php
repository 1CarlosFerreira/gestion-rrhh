<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteReemplazo extends Model
{
    protected $table = 'tramite_reemplazos';

    protected $guarded = ['id'];

    public function getDiasCalendarioAttribute(): int
    {
        return $this->fecha_inicio && $this->fecha_termino ? $this->fecha_inicio->diffInDays($this->fecha_termino) + 1 : 0;
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(AusenciaReemplazable::class, 'ausencia_reemplazable_id');
    }

    public function tipoReemplazo(): BelongsTo
    {
        return $this->belongsTo(TipoReemplazo::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'funcionario_id');
    }

    public function funcionarioVinculo(): BelongsTo
    {
        return $this->belongsTo(PersonaUnidadVinculo::class, 'funcionario_vinculo_id');
    }

    public function reemplazante(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'reemplazante_id');
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
        return ['fecha_inicio' => 'date', 'fecha_termino' => 'date', 'funcionario_snapshot' => 'array', 'reemplazante_snapshot' => 'array', 'remitente_snapshot' => 'array'];
    }
}
