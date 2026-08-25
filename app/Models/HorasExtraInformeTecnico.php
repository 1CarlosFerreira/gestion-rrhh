<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorasExtraInformeTecnico extends Model
{
    protected $table = 'horas_extra_informes_tecnicos';

    protected $guarded = ['id'];

    public function tramiteHorasExtra(): BelongsTo
    {
        return $this->belongsTo(TramiteHorasExtra::class);
    }

    public function preparadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    protected function casts(): array
    {
        return ['horario_diurno' => 'boolean', 'horario_festivo' => 'boolean', 'retribucion_tiempo' => 'boolean', 'retribucion_dinero' => 'boolean', 'prepared_at' => 'datetime'];
    }
}
