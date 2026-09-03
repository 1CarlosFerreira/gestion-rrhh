<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReemplazoRevision extends Model
{
    protected $table = 'reemplazo_revisiones';

    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function clasificacionArea(): BelongsTo
    {
        return $this->belongsTo(ClasificacionArea::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    protected function casts(): array
    {
        return ['cumple_normativa' => 'boolean', 'revisado_at' => 'datetime'];
    }
}
