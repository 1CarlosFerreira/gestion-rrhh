<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docdigital_registros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_generado_id')->nullable()->constrained('documentos_generados')->restrictOnDelete();
            $table->foreignId('adjunto_enviado_id')->nullable()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_envio');
            $table->string('identificador_externo', 190)->nullable();
            $table->text('observacion_envio')->nullable();
            $table->string('estado', 30)->default('ENVIADO');
            $table->unsignedSmallInteger('intento')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('formalizado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_formalizacion')->nullable();
            $table->foreignId('adjunto_final_id')->nullable()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->text('observacion_formalizacion')->nullable();
            $table->timestamps();

            $table->unique(['tramite_id', 'intento']);
            $table->index(['tramite_id', 'is_current']);
            $table->index('documento_generado_id');
            $table->index('adjunto_enviado_id');
            $table->index('registrado_por');
            $table->index('estado');
            $table->index('fecha_envio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docdigital_registros');
    }
};
