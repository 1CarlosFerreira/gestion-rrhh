<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoGenerado extends Model
{
    public const UPDATED_AT = null;

    public const ESTADOS = ['VIGENTE', 'REEMPLAZADO', 'ANULADO'];

    protected $table = 'documentos_generados';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantilla::class, 'documento_plantilla_id');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function adjunto(): BelongsTo
    {
        return $this->belongsTo(TramiteAdjunto::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'metadata' => 'array'];
    }
}
