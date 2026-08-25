<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extra_revisiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planilla_sirh_id')->constrained('horas_extra_planillas_sirh')->restrictOnDelete();
            $table->string('result', 30);
            $table->text('observation')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['planilla_sirh_id', 'reviewed_at'], 'he_revisiones_planilla_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extra_revisiones');
    }
};
