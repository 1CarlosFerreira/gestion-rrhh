<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencias_reemplazables', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('funcionario_id')->nullable()->constrained('personas')->restrictOnDelete();
            $table->foreignId('unidad_servicio_id')->constrained('unidades_servicios')->restrictOnDelete();
            $table->foreignId('tipo_reemplazo_id')->nullable()->constrained('tipos_reemplazo')->restrictOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->text('justificacion')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('funcionario_snapshot')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('close_reason')->nullable();
            $table->timestamps();
            $table->index(['funcionario_id', 'fecha_inicio', 'fecha_termino'], 'ausencia_funcionario_periodo_idx');
        });

        Schema::table('tramite_reemplazos', function (Blueprint $table) {
            $table->foreignId('ausencia_reemplazable_id')->nullable()->after('tramite_id')->constrained('ausencias_reemplazables')->restrictOnDelete();
            $table->index('ausencia_reemplazable_id', 'tramite_reemplazos_ausencia_idx');
        });

        Schema::create('ausencia_reemplazable_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausencia_reemplazable_id')->constrained('ausencias_reemplazables')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action_code', 80);
            $table->text('observation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['ausencia_reemplazable_id', 'occurred_at'], 'ausencia_historial_fecha_idx');
        });

        DB::table('tramite_reemplazos')->orderBy('id')->each(function ($detail): void {
            $tramite = DB::table('tramites')->where('id', $detail->tramite_id)->first();
            $absenceId = DB::table('ausencias_reemplazables')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'funcionario_id' => $detail->funcionario_id,
                'unidad_servicio_id' => $tramite->unidad_servicio_id,
                'tipo_reemplazo_id' => $detail->tipo_reemplazo_id,
                'fecha_inicio' => $detail->fecha_inicio,
                'fecha_termino' => $detail->fecha_termino,
                'justificacion' => $detail->justificacion,
                'created_by' => $tramite->created_by,
                'funcionario_snapshot' => $detail->funcionario_snapshot,
                'created_at' => $detail->created_at ?? $tramite->created_at,
                'updated_at' => $detail->updated_at ?? $tramite->updated_at,
            ]);
            DB::table('tramite_reemplazos')->where('id', $detail->id)->update(['ausencia_reemplazable_id' => $absenceId]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencia_reemplazable_historial');
        Schema::table('tramite_reemplazos', function (Blueprint $table) {
            $table->dropForeign(['ausencia_reemplazable_id']);
            $table->dropIndex('tramite_reemplazos_ausencia_idx');
            $table->dropColumn('ausencia_reemplazable_id');
        });
        Schema::dropIfExists('ausencias_reemplazables');
    }
};
