<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_reemplazo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->index(['orden', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_reemplazo');
    }
};
