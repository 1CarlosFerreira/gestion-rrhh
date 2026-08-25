<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reemplazo_revisiones_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->foreignId('grado_eus_id')->nullable()->constrained('grados_eus')->restrictOnDelete();
            $table->foreignId('clasificacion_area_id')->nullable()->constrained('clasificaciones_area')->restrictOnDelete();
            $table->boolean('cumple_normativa')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazo_revisiones_personal');
    }
};
