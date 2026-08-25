<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_plantillas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100);
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('nombre', 190);
            $table->unsignedSmallInteger('version');
            $table->string('template_path', 500);
            $table->string('mime_type', 150);
            $table->char('sha256', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->unique(['codigo', 'version']);
            $table->index(['tipo_tramite_id', 'tipo_documento_id', 'active'], 'plantillas_tipo_documento_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_plantillas');
    }
};
