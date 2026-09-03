<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_responsables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unidad_organizacional_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('puede_aprobar')->default(false);
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['unidad_organizacional_id', 'tipo', 'vigente_desde', 'vigente_hasta'], 'responsables_unidad_tipo_vigencia_idx');
            $table->index(['persona_id', 'vigente_desde', 'vigente_hasta'], 'responsables_persona_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_responsables');
    }
};
