<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TramiteHorasExtra extends Model
{
    protected $table = 'tramite_horas_extra';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(HorasExtraFuncionario::class);
    }

    public function informeTecnico(): HasOne
    {
        return $this->hasOne(HorasExtraInformeTecnico::class);
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }
}
