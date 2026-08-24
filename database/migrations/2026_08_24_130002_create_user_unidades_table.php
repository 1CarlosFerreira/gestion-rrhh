<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('unidad_servicio_id')->constrained('unidades_servicios')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'unidad_servicio_id']);
            $table->index(['unidad_servicio_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_unidades');
    }
};
