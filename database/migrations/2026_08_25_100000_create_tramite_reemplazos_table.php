<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_reemplazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->foreignId('tipo_reemplazo_id')->nullable()->constrained('tipos_reemplazo')->restrictOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('personas')->restrictOnDelete();
            $table->foreignId('funcionario_vinculo_id')->nullable()->constrained('persona_unidad_vinculos')->restrictOnDelete();
            $table->foreignId('reemplazante_id')->nullable()->constrained('personas')->restrictOnDelete();
            $table->foreignId('estamento_id')->nullable()->constrained('estamentos')->restrictOnDelete();
            $table->foreignId('profesion_id')->nullable()->constrained('profesiones')->restrictOnDelete();
            $table->string('cargo_texto', 190)->nullable();
            $table->text('justificacion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->json('funcionario_snapshot')->nullable();
            $table->json('reemplazante_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_reemplazos');
    }
};
