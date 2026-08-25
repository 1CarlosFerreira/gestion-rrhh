<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HorasExtraPlanillaSirh extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'horas_extra_planillas_sirh';

    protected $guarded = ['id'];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(HorasExtraFuncionario::class, 'horas_extra_funcionario_id');
    }

    public function adjunto(): BelongsTo
    {
        return $this->belongsTo(TramiteAdjunto::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(HorasExtraRevision::class, 'planilla_sirh_id')->latest('reviewed_at');
    }

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'is_current' => 'boolean'];
    }
}
