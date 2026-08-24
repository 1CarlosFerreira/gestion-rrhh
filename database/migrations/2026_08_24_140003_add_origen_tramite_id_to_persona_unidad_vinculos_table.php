<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona_unidad_vinculos', function (Blueprint $table) {
            $table->foreignId('origen_tramite_id')->nullable()->after('status')->constrained('tramites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('persona_unidad_vinculos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_tramite_id');
        });
    }
};
