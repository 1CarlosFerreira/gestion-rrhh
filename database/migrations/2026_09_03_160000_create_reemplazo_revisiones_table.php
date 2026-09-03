<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clasificaciones_area', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('reemplazo_revisiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->unsignedSmallInteger('grado_eus')->nullable();
            $table->foreignId('clasificacion_area_id')->nullable()->constrained('clasificaciones_area')->restrictOnDelete();
            $table->boolean('cumple_normativa')->nullable();
            $table->text('observacion_administrativa')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('revisado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazo_revisiones');
        Schema::dropIfExists('clasificaciones_area');
    }
};
