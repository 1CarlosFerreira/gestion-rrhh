<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocDigitalRegistro extends Model
{
    public const ESTADOS = ['ENVIADO', 'FORMALIZADO', 'ANULADO'];

    protected $table = 'docdigital_registros';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function documentoGenerado(): BelongsTo
    {
        return $this->belongsTo(DocumentoGenerado::class);
    }

    public function adjuntoEnviado(): BelongsTo
    {
        return $this->belongsTo(TramiteAdjunto::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function formalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formalizado_por');
    }

    public function adjuntoFinal(): BelongsTo
    {
        return $this->belongsTo(TramiteAdjunto::class, 'adjunto_final_id');
    }

    protected function casts(): array
    {
        return ['fecha_envio' => 'datetime', 'fecha_formalizacion' => 'datetime', 'is_current' => 'boolean'];
    }
}
