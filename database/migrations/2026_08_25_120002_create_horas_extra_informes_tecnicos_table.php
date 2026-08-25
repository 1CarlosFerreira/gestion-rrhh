<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extra_informes_tecnicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_horas_extra_id')->unique()->constrained('tramite_horas_extra')->restrictOnDelete();
            $table->boolean('horario_diurno')->default(false);
            $table->boolean('horario_festivo')->default(false);
            $table->boolean('retribucion_tiempo')->default(false);
            $table->boolean('retribucion_dinero')->default(false);
            $table->text('justificacion_tecnica')->nullable();
            $table->text('medidas_control')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('prepared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extra_informes_tecnicos');
    }
};
