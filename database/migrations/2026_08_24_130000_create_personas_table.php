<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12)->unique();
            $table->string('nombres', 120);
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['apellido_paterno', 'nombres']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
