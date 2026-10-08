<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table): void {
            $table->foreignId('autoridad_responsabilidad_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('solicitudes_contrato')->whereNull('autoridad_responsabilidad_id')->exists()) {
            throw new RuntimeException('No se puede revertir: existen solicitudes con autoridad resuelta desde varias responsabilidades.');
        }

        Schema::table('solicitudes_contrato', function (Blueprint $table): void {
            $table->foreignId('autoridad_responsabilidad_id')->nullable(false)->change();
        });
    }
};
