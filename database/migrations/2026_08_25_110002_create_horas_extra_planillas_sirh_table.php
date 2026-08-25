<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extra_planillas_sirh', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('horas_extra_funcionario_id')->constrained('horas_extra_funcionarios')->restrictOnDelete();
            $table->foreignId('adjunto_id')->unique()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['horas_extra_funcionario_id', 'version'], 'he_planillas_funcionario_version_unique');
            $table->index(['horas_extra_funcionario_id', 'is_current'], 'he_planillas_funcionario_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extra_planillas_sirh');
    }
};
