<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_unidad_accesos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unidad_organizacional_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->string('alcance', 30);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'vigente_desde', 'vigente_hasta'], 'accesos_user_vigencia_idx');
            $table->index(['unidad_organizacional_id', 'alcance', 'vigente_desde', 'vigente_hasta'], 'accesos_unidad_alcance_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_unidad_accesos');
    }
};
