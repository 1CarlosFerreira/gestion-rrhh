<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUnidad extends Model
{
    protected $table = 'user_unidades';

    protected $fillable = ['user_id', 'unidad_servicio_id', 'active', 'valid_from', 'valid_to'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_servicio_id');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}
