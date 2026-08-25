<?php

namespace App\Actions\HorasExtraordinarias;

use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Models\HorasExtraFuncionario;
use App\Models\HorasExtraPlanillaSirh;
use App\Models\TipoDocumento;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CargarPlanillaSirh
{
    public function __construct(private readonly CargarAdjunto $cargarAdjunto) {}

    public function execute(HorasExtraFuncionario $funcionario, UploadedFile $file, User $user): HorasExtraPlanillaSirh
    {
        if (! $user->can('horas_extra.cargar_planilla')) {
            throw new AuthorizationException;
        }
        $funcionario->loadMissing('tramiteHorasExtra.tramite.estadoTramite', 'planillaVigente.adjunto');
        $tramite = $funcionario->tramiteHorasExtra->tramite->fresh(['estadoTramite']);
        if (! in_array($tramite->estadoTramite->codigo, ['ENVIADA_GESTION_PERSONAS', 'EN_CORRECCION_SIRH'], true)) {
            throw ValidationException::withMessages(['archivo' => 'No se puede cargar una planilla en el estado actual.']);
        }
        if ($file->getMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages(['archivo' => 'La Planilla SIRH debe ser un archivo PDF.']);
        }
        $previous = $funcionario->planillaVigente;
        if ($previous && $tramite->estadoTramite->codigo !== 'EN_CORRECCION_SIRH') {
            throw ValidationException::withMessages(['archivo' => 'Una nueva versión solo puede cargarse durante la corrección SIRH.']);
        }
        $tipo = TipoDocumento::query()->where('codigo', 'PLANILLA_SIRH')->where('active', true)->firstOrFail();

        return DB::transaction(function () use ($funcionario, $file, $user, $tramite, $previous, $tipo): HorasExtraPlanillaSirh {
            $lockedPrevious = $previous ? HorasExtraPlanillaSirh::query()->lockForUpdate()->findOrFail($previous->id) : null;
            $adjuntoAnterior = $lockedPrevious ? TramiteAdjunto::query()->findOrFail($lockedPrevious->adjunto_id) : null;
            $adjunto = $this->cargarAdjunto->execute($tramite, $file, $user, $tipo->id, $funcionario->persona_id, $adjuntoAnterior);
            if ($lockedPrevious) {
                $lockedPrevious->update(['is_current' => false]);
            }
            $planilla = $funcionario->planillas()->create([
                'adjunto_id' => $adjunto->id, 'version' => ($lockedPrevious?->version ?? 0) + 1,
                'uploaded_by' => $user->id, 'is_current' => true,
            ]);
            $funcionario->update(['review_status' => 'PENDIENTE']);
            $tramite->historial()->create([
                'user_id' => $user->id, 'action_code' => $lockedPrevious ? 'PLANILLA_SIRH_VERSIONADA' : 'PLANILLA_SIRH_CARGADA',
                'metadata' => ['participante_id' => $funcionario->id, 'planilla_id' => $planilla->id, 'adjunto_id' => $adjunto->id, 'version' => $planilla->version], 'occurred_at' => now(),
            ]);

            return $planilla;
        });
    }
}
