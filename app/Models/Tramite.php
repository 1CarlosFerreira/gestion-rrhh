<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tramite extends Model
{
    protected $fillable = ['public_id', 'codigo', 'tipo_tramite_id', 'estado_tramite_id', 'created_by', 'submitted_at', 'finalized_at'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    public function estadoTramite(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TramiteHistorial::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(TramiteAdjunto::class)->latest('created_at');
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class)->latest('generated_at');
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'finalized_at' => 'datetime'];
    }
}
