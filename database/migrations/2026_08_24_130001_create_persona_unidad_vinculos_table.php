<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_unidad_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('unidad_servicio_id')->constrained('unidades_servicios')->restrictOnDelete();
            $table->foreignId('estamento_id')->nullable()->constrained('estamentos')->restrictOnDelete();
            $table->foreignId('profesion_id')->nullable()->constrained('profesiones')->restrictOnDelete();
            $table->string('cargo_texto', 190)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('ACTIVO');
            $table->timestamps();
            $table->index(['persona_id', 'status']);
            $table->index(['unidad_servicio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_unidad_vinculos');
    }
};
