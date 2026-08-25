<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extra_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_horas_extra_id')->constrained('tramite_horas_extra')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->unsignedInteger('daytime_minutes')->nullable();
            $table->unsignedInteger('night_festive_minutes')->nullable();
            $table->unsignedInteger('total_minutes')->nullable();
            $table->string('review_status', 30)->default('PENDIENTE');
            $table->timestamps();
            $table->unique(['tramite_horas_extra_id', 'persona_id'], 'he_funcionarios_tramite_persona_unique');
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extra_funcionarios');
    }
};
