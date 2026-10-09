<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos_transitorios', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('solicitud_origen_id')->constrained('solicitudes_contrato')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['solicitud_origen_id', 'created_at'], 'respaldos_solicitud_creacion_idx');
        });

        Schema::create('respaldo_transitorio_versiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('respaldo_id')->constrained('respaldos_transitorios')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('funcionario_origen_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('unidad_origen_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->string('motivo', 255);
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('referencia_externa', 255)->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->text('motivo_rectificacion')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['respaldo_id', 'version'], 'respaldo_version_numero_unique');
            $table->unique(['respaldo_id', 'id'], 'respaldo_version_pertenencia_unique');
            $table->index(['funcionario_origen_id', 'fecha_desde', 'fecha_hasta'], 'respaldo_origen_periodo_idx');
        });

        Schema::create('respaldo_afectaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('respaldo_id')->constrained('respaldos_transitorios')->restrictOnDelete();
            $table->unsignedBigInteger('respaldo_version_id');
            $table->foreignId('solicitud_contrato_id')->constrained('solicitudes_contrato')->restrictOnDelete();
            $table->dateTime('comprometido_at');
            $table->foreignId('comprometido_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('liberado_at')->nullable();
            $table->foreignId('liberado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_liberacion')->nullable();
            $table->unsignedBigInteger('respaldo_activo_id')->nullable()->virtualAs('case when liberado_at is null then respaldo_id else null end');
            $table->unsignedBigInteger('solicitud_activa_id')->nullable()->virtualAs('case when liberado_at is null then solicitud_contrato_id else null end');
            $table->timestamps();
            $table->foreign(['respaldo_id', 'respaldo_version_id'], 'afectacion_version_fk')
                ->references(['respaldo_id', 'id'])->on('respaldo_transitorio_versiones')->restrictOnDelete();
            $table->unique('respaldo_activo_id', 'afectacion_respaldo_activo_unique');
            $table->unique('solicitud_activa_id', 'afectacion_solicitud_activa_unique');
            $table->index(['respaldo_id', 'comprometido_at'], 'afectacion_respaldo_historial_idx');
            $table->index(['solicitud_contrato_id', 'comprometido_at'], 'afectacion_solicitud_historial_idx');
        });

        Schema::create('reserva_persona_periodos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('afectacion_id')->constrained('respaldo_afectaciones')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->dateTime('reservado_at');
            $table->foreignId('reservado_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('liberado_at')->nullable();
            $table->foreignId('liberado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_liberacion')->nullable();
            $table->unsignedBigInteger('afectacion_activa_id')->nullable()->virtualAs('case when liberado_at is null then afectacion_id else null end');
            $table->timestamps();
            $table->unique('afectacion_activa_id', 'reserva_afectacion_activa_unique');
            $table->index(['persona_id', 'fecha_desde', 'fecha_hasta'], 'reserva_persona_periodo_idx');
            $table->index(['afectacion_id', 'reservado_at'], 'reserva_afectacion_historial_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE respaldo_transitorio_versiones ADD CONSTRAINT respaldo_version_numero_chk CHECK (version >= 1)');
            DB::statement('ALTER TABLE respaldo_transitorio_versiones ADD CONSTRAINT respaldo_version_fechas_chk CHECK (fecha_desde <= fecha_hasta)');
            DB::statement('ALTER TABLE respaldo_afectaciones ADD CONSTRAINT afectacion_liberacion_chk CHECK ((liberado_at IS NULL AND liberado_por IS NULL AND motivo_liberacion IS NULL) OR (liberado_at >= comprometido_at AND liberado_por IS NOT NULL AND motivo_liberacion IS NOT NULL))');
            DB::statement('ALTER TABLE reserva_persona_periodos ADD CONSTRAINT reserva_fechas_chk CHECK (fecha_desde <= fecha_hasta)');
            DB::statement('ALTER TABLE reserva_persona_periodos ADD CONSTRAINT reserva_liberacion_chk CHECK ((liberado_at IS NULL AND liberado_por IS NULL AND motivo_liberacion IS NULL) OR (liberado_at >= reservado_at AND liberado_por IS NOT NULL AND motivo_liberacion IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_persona_periodos');
        Schema::dropIfExists('respaldo_afectaciones');
        Schema::dropIfExists('respaldo_transitorio_versiones');
        Schema::dropIfExists('respaldos_transitorios');
    }
};
