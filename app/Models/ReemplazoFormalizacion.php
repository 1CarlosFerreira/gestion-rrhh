<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReemplazoFormalizacion extends Model
{
    protected $table = 'reemplazo_formalizaciones';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function documentoGenerado(): BelongsTo
    {
        return $this->belongsTo(DocumentoGenerado::class);
    }

    public function estamento(): BelongsTo
    {
        return $this->belongsTo(Estamento::class);
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(Profesion::class);
    }

    public function calidadContractual(): BelongsTo
    {
        return $this->belongsTo(CalidadContractual::class);
    }

    public function adjunto(): BelongsTo
    {
        return $this->belongsTo(TramiteAdjunto::class);
    }

    public function formalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formalizado_por');
    }

    protected function casts(): array
    {
        return ['grado_eus' => 'integer', 'formalizado_at' => 'datetime'];
    }
}
