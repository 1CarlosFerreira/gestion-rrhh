<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_organizacionales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('tipo_unidad_organizacional_id')->constrained('tipos_unidad_organizacional')->restrictOnDelete();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 190)->index();
            $table->string('sigla', 50)->nullable()->index();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('participa_en_aprobacion')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->index(['parent_id', 'activo', 'orden']);
            $table->index(['tipo_unidad_organizacional_id', 'activo'], 'unidades_tipo_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_organizacionales');
    }
};
