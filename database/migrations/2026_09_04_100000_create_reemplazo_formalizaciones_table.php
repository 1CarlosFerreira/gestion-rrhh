<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reemplazo_formalizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_generado_id')->unique()->constrained('documentos_generados')->restrictOnDelete();
            $table->foreignId('estamento_id')->constrained('estamentos')->restrictOnDelete();
            $table->foreignId('profesion_id')->nullable()->constrained('profesiones')->restrictOnDelete();
            $table->foreignId('calidad_contractual_id')->constrained('calidades_contractuales')->restrictOnDelete();
            $table->string('cargo_funcion', 200);
            $table->string('cargo_funcion_normalizado', 200);
            $table->unsignedSmallInteger('grado_eus')->nullable();
            $table->string('identificador_externo', 255)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('adjunto_id')->nullable()->unique()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->foreignId('formalizado_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('formalizado_at');
            $table->timestamps();
            $table->index(['calidad_contractual_id', 'formalizado_at'], 'formalizaciones_calidad_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazo_formalizaciones');
    }
};
