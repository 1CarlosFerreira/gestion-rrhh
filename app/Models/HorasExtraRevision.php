<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorasExtraRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'horas_extra_revisiones';

    protected $guarded = ['id'];

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(HorasExtraPlanillaSirh::class, 'planilla_sirh_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las revisiones de planillas son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las revisiones de planillas son inmutables.'));
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
