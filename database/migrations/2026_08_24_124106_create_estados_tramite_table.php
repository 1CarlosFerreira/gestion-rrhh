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
        Schema::create('estados_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->string('codigo', 100);
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['tipo_tramite_id', 'codigo']);
            $table->index(['tipo_tramite_id', 'activo', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_tramite');
    }
};
