<?php

namespace App\Actions\HorasExtraordinarias;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class HorasExtraTransitionGuard implements TramiteTransitionGuard
{
    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void
    {
        if ($tramite->tipoTramite()->value('codigo') !== 'HORAS_EXTRAORDINARIAS') {
            return;
        }
        $detail = $tramite->horasExtra()->with(['funcionarios.planillaVigente.adjunto', 'funcionarios.planillaVigente.revisiones'])->first();
        if (! $detail) {
            throw ValidationException::withMessages(['tramite' => 'Falta el detalle de Horas Extraordinarias.']);
        }
        $participants = $detail->funcionarios;
        if ($transicion->codigo_accion === 'ENVIAR_A_GESTION_PERSONAS') {
            $expected = CarbonImmutable::create($detail->year, $detail->month, 1);
            if (! $tramite->unidadServicio()->where('activo', true)->exists() || $participants->isEmpty() || $detail->month < 1 || $detail->month > 12 || ! $detail->period_start->isSameDay($expected) || ! $detail->period_end->isSameDay($expected->endOfMonth())) {
                throw ValidationException::withMessages(['tramite' => 'La unidad, período y al menos un funcionario son obligatorios y deben ser válidos.']);
            }
        }
        if (in_array($transicion->codigo_accion, ['PUBLICAR_PLANILLAS', 'VOLVER_A_PLANILLA_DISPONIBLE'], true)) {
            foreach ($participants as $participant) {
                if (! $participant->planillaVigente || $participant->planillaVigente->adjunto?->mime_type !== 'application/pdf' || $participant->daytime_minutes === null || $participant->night_festive_minutes === null || $participant->total_minutes !== $participant->daytime_minutes + $participant->night_festive_minutes) {
                    throw ValidationException::withMessages(['planillas' => 'Todos los funcionarios deben tener PDF vigente y horas estructuradas válidas.']);
                }
                if ($transicion->codigo_accion === 'VOLVER_A_PLANILLA_DISPONIBLE' && $participant->review_status === 'PENDIENTE' && $participant->planillaVigente->revisiones()->exists()) {
                    throw ValidationException::withMessages(['planillas' => 'La planilla pendiente debe corresponder a una versión nueva aún no revisada.']);
                }
                if ($transicion->codigo_accion === 'VOLVER_A_PLANILLA_DISPONIBLE' && $participant->review_status === 'OBSERVADA') {
                    throw ValidationException::withMessages(['planillas' => 'Toda planilla observada debe reemplazarse antes de volver a revisión.']);
                }
            }
        }
        if (str_starts_with($transicion->codigo_accion, 'FINALIZAR_REVISION_')) {
            if ($participants->isEmpty() || $participants->contains(fn ($participant) => $participant->review_status === 'PENDIENTE')) {
                throw ValidationException::withMessages(['revision' => 'Todos los funcionarios deben tener una decisión antes de finalizar.']);
            }
            $allConforme = $participants->every(fn ($participant) => $participant->review_status === 'CONFORME' && $participant->planillaVigente?->revisiones()->where('result', 'CONFORME')->exists());
            if (($transicion->codigo_accion === 'FINALIZAR_REVISION_CONFORME') !== $allConforme) {
                throw ValidationException::withMessages(['revision' => 'El resultado global no coincide con las decisiones vigentes.']);
            }
        }
        if ($transicion->codigo_accion === 'GENERAR_INFORME_TECNICO' && ! $tramite->documentosGenerados()->where('status', 'VIGENTE')->whereHas('tipoDocumento', fn ($query) => $query->where('codigo', 'INFORME_TECNICO'))->exists()) {
            throw ValidationException::withMessages(['documento' => 'Debe generarse correctamente el Informe Técnico antes de avanzar.']);
        }
    }
}
