<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReemplazoRevisionPersonal extends Model
{
    protected $table = 'reemplazo_revisiones_personal';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function gradoEus(): BelongsTo
    {
        return $this->belongsTo(GradoEus::class);
    }

    public function clasificacionArea(): BelongsTo
    {
        return $this->belongsTo(ClasificacionArea::class);
    }

    public function completadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    protected function casts(): array
    {
        return ['grado_eus_informado' => 'integer', 'cumple_normativa' => 'boolean', 'completed_at' => 'datetime'];
    }
}
