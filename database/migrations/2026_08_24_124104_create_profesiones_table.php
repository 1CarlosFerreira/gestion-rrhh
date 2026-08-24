<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estamento_id')->nullable()->constrained('estamentos')->restrictOnDelete();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 150)->index();
            $table->boolean('activo')->default(true);
            $table->index(['estamento_id', 'activo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profesiones');
    }
};
