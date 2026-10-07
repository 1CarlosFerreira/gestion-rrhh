<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->string('modalidad', 20);
            $table->foreignId('unidad_solicitante_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('autoridad_persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('autoridad_responsabilidad_id')->constrained('unidad_responsables')->restrictOnDelete();
            $table->string('autoridad_contexto', 60);
            $table->dateTime('autoridad_resuelta_at');
            $table->foreignId('unidad_origen_id')->nullable()->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('unidad_destino_id')->nullable()->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->timestamps();
            $table->index(['modalidad', 'unidad_solicitante_id'], 'solicitudes_modalidad_solicitante_idx');
            $table->index(['unidad_origen_id', 'unidad_destino_id'], 'solicitudes_origen_destino_idx');
            $table->index(['autoridad_persona_id', 'autoridad_resuelta_at'], 'solicitudes_autoridad_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_contrato');
    }
};
