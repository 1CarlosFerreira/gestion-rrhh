<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AusenciaReemplazable extends Model
{
    protected $table = 'ausencias_reemplazables';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'funcionario_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_servicio_id');
    }

    public function tipoReemplazo(): BelongsTo
    {
        return $this->belongsTo(TipoReemplazo::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function coberturas(): HasMany
    {
        return $this->hasMany(TramiteReemplazo::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(AusenciaReemplazableHistorial::class)->orderBy('occurred_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_termino' => 'date', 'funcionario_snapshot' => 'array', 'closed_at' => 'datetime'];
    }
}
