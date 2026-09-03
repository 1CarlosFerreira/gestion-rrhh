<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_reemplazos', function (Blueprint $table): void {
            $table->foreignId('funcionario_id')->nullable()->change();
            $table->foreignId('tipo_reemplazo_id')->nullable()->change();
            $table->date('fecha_funcionario_desde')->nullable()->change();
            $table->date('fecha_funcionario_hasta')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tramite_reemplazos', function (Blueprint $table): void {
            $table->foreignId('funcionario_id')->nullable(false)->change();
            $table->foreignId('tipo_reemplazo_id')->nullable(false)->change();
            $table->date('fecha_funcionario_desde')->nullable(false)->change();
            $table->date('fecha_funcionario_hasta')->nullable(false)->change();
        });
    }
};
