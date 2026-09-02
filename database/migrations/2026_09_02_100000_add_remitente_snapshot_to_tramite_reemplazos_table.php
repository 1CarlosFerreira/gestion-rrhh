<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_reemplazos', fn (Blueprint $table) => $table->json('remitente_snapshot')->nullable()->after('reemplazante_snapshot'));
    }

    public function down(): void
    {
        Schema::table('tramite_reemplazos', fn (Blueprint $table) => $table->dropColumn('remitente_snapshot'));
    }
};
