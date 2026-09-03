<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_reemplazos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('reemplazante_id')->nullable()->constrained('personas')->restrictOnDelete();
            $table->foreignId('tipo_reemplazo_id')->constrained('tipos_reemplazo')->restrictOnDelete();
            $table->date('fecha_funcionario_desde');
            $table->date('fecha_funcionario_hasta');
            $table->date('fecha_reemplazante_desde')->nullable();
            $table->date('fecha_reemplazante_hasta')->nullable();
            $table->text('justificacion')->nullable();
            $table->timestamps();
            $table->index(['funcionario_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta'], 'reemplazos_funcionario_periodo_idx');
            $table->index(['reemplazante_id', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta'], 'reemplazos_reemplazante_periodo_idx');
            $table->index(['tipo_reemplazo_id', 'fecha_funcionario_desde'], 'reemplazos_tipo_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_reemplazos');
    }
};
