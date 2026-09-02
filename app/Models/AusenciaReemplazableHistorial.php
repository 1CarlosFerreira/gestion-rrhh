<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AusenciaReemplazableHistorial extends Model
{
    protected $table = 'ausencia_reemplazable_historial';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(AusenciaReemplazable::class, 'ausencia_reemplazable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
