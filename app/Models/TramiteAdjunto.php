<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TramiteAdjunto extends Model
{
    public const UPDATED_AT = null;

    public const ESTADOS = ['ACTIVO', 'REEMPLAZADO', 'ANULADO'];

    protected $table = 'tramite_adjuntos';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reemplazaA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_adjunto_id');
    }

    public function reemplazadoPor(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_adjunto_id');
    }

    public function documentoGenerado(): HasOne
    {
        return $this->hasOne(DocumentoGenerado::class, 'adjunto_id');
    }

    public function formalizacionReemplazo(): HasOne
    {
        return $this->hasOne(ReemplazoFormalizacion::class, 'adjunto_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVO');
    }

    public function scopeDelTipo(Builder $query, int $tipo): Builder
    {
        return $query->where('tipo_documento_id', $tipo);
    }

    public function scopeVersiones(Builder $query): Builder
    {
        return $query->orderByDesc('version');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Los adjuntos no se eliminan físicamente desde la aplicación.'));
    }
}
