<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitos_documentales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('tipo_reemplazo_id')->nullable()->constrained('tipos_reemplazo')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->boolean('obligatorio')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tipo_tramite_id', 'tipo_reemplazo_id', 'active'], 'requisitos_tipo_reemplazo_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos_documentales');
    }
};
