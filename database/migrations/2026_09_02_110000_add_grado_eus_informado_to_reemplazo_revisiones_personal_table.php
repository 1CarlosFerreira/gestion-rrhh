<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reemplazo_revisiones_personal', function (Blueprint $table): void {
            $table->unsignedSmallInteger('grado_eus_informado')->nullable()->after('grado_eus_id');
        });

        DB::table('reemplazo_revisiones_personal')
            ->join('grados_eus', 'grados_eus.id', '=', 'reemplazo_revisiones_personal.grado_eus_id')
            ->whereNull('reemplazo_revisiones_personal.grado_eus_informado')
            ->whereNotNull('grados_eus.grado')
            ->select('reemplazo_revisiones_personal.id', 'grados_eus.grado')
            ->get()
            ->each(fn (object $row) => DB::table('reemplazo_revisiones_personal')->where('id', $row->id)->update(['grado_eus_informado' => $row->grado]));
    }

    public function down(): void
    {
        Schema::table('reemplazo_revisiones_personal', fn (Blueprint $table) => $table->dropColumn('grado_eus_informado'));
    }
};
