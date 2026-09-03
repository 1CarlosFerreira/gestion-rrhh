<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table): void {
            $table->foreignId('unidad_organizacional_id')->nullable()->after('estado_tramite_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->index(['unidad_organizacional_id', 'created_at'], 'tramites_unidad_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table): void {
            $table->dropIndex('tramites_unidad_created_idx');
            $table->dropConstrainedForeignId('unidad_organizacional_id');
        });
    }
};
