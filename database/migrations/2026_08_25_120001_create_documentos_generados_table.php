<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_generados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_plantilla_id')->constrained('documento_plantillas')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->foreignId('adjunto_id')->unique()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('generated_at');
            $table->string('status', 30)->default('VIGENTE');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tramite_id', 'tipo_documento_id', 'version'], 'documentos_tramite_tipo_version_unique');
            $table->index(['tramite_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_generados');
    }
};
