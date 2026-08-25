<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tramite extends Model
{
    protected $fillable = ['public_id', 'codigo', 'tipo_tramite_id', 'unidad_servicio_id', 'estado_tramite_id', 'created_by', 'submitted_at', 'finalized_at'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    public function estadoTramite(): BelongsTo
    {
        return $this->belongsTo(EstadoTramite::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TramiteHistorial::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(TramiteAdjunto::class)->latest('created_at');
    }

    public function reemplazo(): HasOne
    {
        return $this->hasOne(TramiteReemplazo::class);
    }

    public function revisionReemplazo(): HasOne
    {
        return $this->hasOne(ReemplazoRevisionPersonal::class);
    }

    public function horasExtra(): HasOne
    {
        return $this->hasOne(TramiteHorasExtra::class);
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class)->latest('generated_at');
    }

    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->can('tramites.ver_todos')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            if ($user->can('tramites.ver_propios')) {
                $query->orWhere('created_by', $user->id);
            }
            if ($user->can('tramites.ver_unidad')) {
                $query->orWhereIn('unidad_servicio_id', $user->unidadesHabilitadas()->select('unidades_servicios.id'));
            }
            if (! $user->canAny(['tramites.ver_propios', 'tramites.ver_unidad'])) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'finalized_at' => 'datetime'];
    }
}
