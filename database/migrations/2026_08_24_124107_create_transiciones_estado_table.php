<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transiciones_estado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('estado_origen_id')->constrained('estados_tramite')->restrictOnDelete();
            $table->foreignId('estado_destino_id')->constrained('estados_tramite')->restrictOnDelete();
            $table->string('codigo_accion', 100);
            $table->string('permiso_requerido', 150)->nullable();
            $table->boolean('requiere_observacion')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['tipo_tramite_id', 'estado_origen_id', 'codigo_accion'], 'transicion_tipo_origen_accion_unique');
            $table->index(['estado_origen_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transiciones_estado');
    }
};
