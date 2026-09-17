<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_reemplazos', function (Blueprint $table): void {
            $table->foreignId('reemplazante_estamento_id')->nullable()->after('reemplazante_id')->constrained('estamentos')->restrictOnDelete();
            $table->foreignId('reemplazante_profesion_id')->nullable()->after('reemplazante_estamento_id')->constrained('profesiones')->restrictOnDelete();
            $table->foreignId('reemplazante_calidad_contractual_id')->nullable()->after('reemplazante_profesion_id')->constrained('calidades_contractuales')->restrictOnDelete();
            $table->string('reemplazante_cargo_funcion', 200)->nullable()->after('reemplazante_calidad_contractual_id');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_reemplazos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reemplazante_profesion_id');
            $table->dropConstrainedForeignId('reemplazante_estamento_id');
            $table->dropConstrainedForeignId('reemplazante_calidad_contractual_id');
            $table->dropColumn('reemplazante_cargo_funcion');
        });
    }
};
