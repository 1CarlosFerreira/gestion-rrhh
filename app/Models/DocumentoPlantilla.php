<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoPlantilla extends Model
{
    protected $table = 'documento_plantillas';

    protected $guarded = ['id'];

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class, 'documento_plantilla_id');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}
