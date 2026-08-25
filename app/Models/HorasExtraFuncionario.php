<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HorasExtraFuncionario extends Model
{
    public const ESTADOS_REVISION = ['PENDIENTE', 'OBSERVADA', 'CONFORME'];

    protected $table = 'horas_extra_funcionarios';

    protected $guarded = ['id'];

    public function tramiteHorasExtra(): BelongsTo
    {
        return $this->belongsTo(TramiteHorasExtra::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function planillas(): HasMany
    {
        return $this->hasMany(HorasExtraPlanillaSirh::class);
    }

    public function planillaVigente()
    {
        return $this->hasOne(HorasExtraPlanillaSirh::class)->where('is_current', true);
    }
}
