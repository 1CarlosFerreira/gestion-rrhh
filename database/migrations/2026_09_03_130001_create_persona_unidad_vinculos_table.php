<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_unidad_vinculos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('unidad_organizacional_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('estamento_id')->constrained('estamentos')->restrictOnDelete();
            $table->foreignId('profesion_id')->nullable()->constrained('profesiones')->restrictOnDelete();
            $table->foreignId('calidad_contractual_id')->constrained('calidades_contractuales')->restrictOnDelete();
            $table->string('cargo_funcion', 200);
            $table->string('cargo_funcion_normalizado', 200);
            $table->unsignedSmallInteger('grado_eus')->nullable();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->string('origen', 30);
            $table->foreignId('origen_tramite_id')->nullable()->constrained('tramites')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('origen_tramite_id', 'vinculos_origen_tramite_unique');
            $table->index(['persona_id', 'vigente_desde', 'vigente_hasta'], 'vinculos_persona_vigencia_idx');
            $table->index(['unidad_organizacional_id', 'vigente_desde', 'vigente_hasta'], 'vinculos_unidad_vigencia_idx');
            $table->index(['calidad_contractual_id', 'vigente_desde'], 'vinculos_calidad_vigencia_idx');
            $table->index(['persona_id', 'unidad_organizacional_id', 'calidad_contractual_id', 'cargo_funcion_normalizado'], 'vinculos_equivalencia_idx');
            $table->index(['origen', 'vigente_desde'], 'vinculos_origen_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_unidad_vinculos');
    }
};
