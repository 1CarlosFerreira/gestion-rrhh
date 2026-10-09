<?php

namespace App\Models;

use App\Enums\ModalidadSolicitudContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class RespaldoTransitorio extends Model
{
    protected $table = 'respaldos_transitorios';

    protected $fillable = ['solicitud_origen_id', 'created_by'];

    public function solicitudOrigen(): BelongsTo
    {
        return $this->belongsTo(SolicitudContrato::class, 'solicitud_origen_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(RespaldoTransitorioVersion::class, 'respaldo_id')->orderBy('version');
    }

    public function versionActual(): HasOne
    {
        return $this->hasOne(RespaldoTransitorioVersion::class, 'respaldo_id')->ofMany('version', 'max');
    }

    public function afectaciones(): HasMany
    {
        return $this->hasMany(RespaldoAfectacion::class, 'respaldo_id')->orderBy('comprometido_at')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $respaldo): void {
            if (SolicitudContrato::query()->find($respaldo->solicitud_origen_id)?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
                throw new LogicException('Un respaldo transitorio requiere una solicitud transitoria.');
            }
            $respaldo->public_id ??= (string) Str::ulid();
        });
        static::updating(fn () => throw new LogicException('La identidad del respaldo es inmutable.'));
        static::deleting(fn () => throw new LogicException('Los respaldos no se eliminan físicamente.'));
    }
}
