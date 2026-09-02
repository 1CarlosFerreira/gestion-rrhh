<?php

namespace App\Services\Reemplazos;

use App\Models\AusenciaReemplazable;
use App\Models\TramiteReemplazo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CoberturaAusenciaService
{
    public const NON_RESERVING_STATES = ['BORRADOR', 'CANCELADA', 'CANCELADO', 'ANULADA', 'ANULADO'];

    public function validationErrors(TramiteReemplazo $coverage): array
    {
        $absence = $coverage->ausencia;
        $errors = [];
        if (! $absence?->fecha_inicio) {
            $errors['fecha_inicio_ausencia'] = 'La fecha de inicio de la ausencia es obligatoria.';
        }
        if (! $absence?->fecha_termino) {
            $errors['fecha_termino_ausencia'] = 'La fecha de término de la ausencia es obligatoria.';
        }
        if ($absence?->fecha_inicio && $absence?->fecha_termino && $absence->fecha_inicio->gt($absence->fecha_termino)) {
            $errors['fecha_termino_ausencia'] = 'La fecha de término de la ausencia debe ser posterior o igual a su inicio.';
        }
        if (! $coverage->fecha_inicio) {
            $errors['fecha_inicio'] = 'La fecha de inicio del reemplazo es obligatoria.';
        }
        if (! $coverage->fecha_termino) {
            $errors['fecha_termino'] = 'La fecha de término del reemplazo es obligatoria.';
        }
        if ($coverage->fecha_inicio && $coverage->fecha_termino && $coverage->fecha_inicio->gt($coverage->fecha_termino)) {
            $errors['fecha_termino'] = 'La fecha de término del reemplazo debe ser posterior o igual a su inicio.';
        }
        if ($absence?->fecha_inicio && $coverage->fecha_inicio && $coverage->fecha_inicio->lt($absence->fecha_inicio)) {
            $errors['fecha_inicio'] = 'La fecha de inicio del reemplazo no puede ser anterior al inicio de la ausencia.';
        }
        if ($absence?->fecha_termino && $coverage->fecha_termino && $coverage->fecha_termino->gt($absence->fecha_termino)) {
            $errors['fecha_termino'] = 'La fecha de término del reemplazo no puede superar el término de la ausencia.';
        }

        return $errors;
    }

    public function conflictingCoverage(TramiteReemplazo $coverage, bool $lock = false): ?TramiteReemplazo
    {
        if (! $coverage->fecha_inicio || ! $coverage->fecha_termino || ! $coverage->ausencia_reemplazable_id) {
            return null;
        }

        return TramiteReemplazo::query()
            ->where('ausencia_reemplazable_id', $coverage->ausencia_reemplazable_id)
            ->whereKeyNot($coverage->id)
            ->whereDate('fecha_inicio', '<=', $coverage->fecha_termino)
            ->whereDate('fecha_termino', '>=', $coverage->fecha_inicio)
            ->whereHas('tramite.estadoTramite', fn ($query) => $query->whereNotIn('codigo', self::NON_RESERVING_STATES))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function reservedCoverages(AusenciaReemplazable $absence): Collection
    {
        return $absence->coberturas()->with(['tramite.estadoTramite', 'reemplazante'])
            ->whereHas('tramite.estadoTramite', fn ($query) => $query->whereNotIn('codigo', self::NON_RESERVING_STATES))
            ->whereNotNull('fecha_inicio')->whereNotNull('fecha_termino')->orderBy('fecha_inicio')->get();
    }

    public function inclusiveDays($start, $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        return CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
    }

    public function availableIntervals(AusenciaReemplazable $absence): array
    {
        if (! $absence->fecha_inicio || ! $absence->fecha_termino) {
            return [];
        }
        $cursor = CarbonImmutable::parse($absence->fecha_inicio);
        $end = CarbonImmutable::parse($absence->fecha_termino);
        $intervals = [];

        foreach ($this->reservedCoverages($absence) as $coverage) {
            $start = CarbonImmutable::parse($coverage->fecha_inicio)->max($cursor);
            if ($cursor->lt($start)) {
                $intervals[] = ['inicio' => $cursor, 'termino' => $start->subDay(), 'dias' => $this->inclusiveDays($cursor, $start->subDay())];
            }
            $coverageEnd = CarbonImmutable::parse($coverage->fecha_termino)->addDay();
            if ($coverageEnd->gt($cursor)) {
                $cursor = $coverageEnd;
            }
        }
        if ($cursor->lte($end)) {
            $intervals[] = ['inicio' => $cursor, 'termino' => $end, 'dias' => $this->inclusiveDays($cursor, $end)];
        }

        return $intervals;
    }

    public function summary(AusenciaReemplazable $absence): array
    {
        $total = $this->inclusiveDays($absence->fecha_inicio, $absence->fecha_termino);
        $available = collect($this->availableIntervals($absence))->sum('dias');
        $covered = max(0, $total - $available);
        $documented = $this->reservedCoverages($absence)
            ->filter(fn ($coverage) => $coverage->tramite->estadoTramite->codigo === 'DOCUMENTO_GENERADO')
            ->sum(fn ($coverage) => $this->inclusiveDays($coverage->fecha_inicio, $coverage->fecha_termino));
        $status = $absence->closed_at ? 'Cerrada' : ($covered === 0 ? 'Sin cobertura' : ($available === 0 ? 'Completamente cubierta' : 'Parcialmente cubierta'));

        return [
            'total_dias' => $total,
            'dias_documentados' => $documented,
            'dias_en_tramite' => max(0, $covered - $documented),
            'dias_disponibles' => $available,
            'porcentaje' => $total > 0 ? round(($covered / $total) * 100, 1) : 0,
            'estado' => $status,
            'intervalos_disponibles' => $this->availableIntervals($absence),
        ];
    }
}
