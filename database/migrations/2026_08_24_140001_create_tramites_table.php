<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('codigo', 30)->unique();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('unidad_servicio_id')->constrained('unidades_servicios')->restrictOnDelete();
            $table->foreignId('estado_tramite_id')->constrained('estados_tramite')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['tipo_tramite_id', 'estado_tramite_id', 'created_at']);
            $table->index(['unidad_servicio_id', 'estado_tramite_id', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};
