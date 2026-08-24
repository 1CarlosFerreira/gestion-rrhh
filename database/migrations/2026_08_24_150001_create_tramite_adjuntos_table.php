<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->nullable()->constrained('tipos_documento')->restrictOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_name', 100);
            $table->string('storage_path', 500)->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('replaces_adjunto_id')->nullable()->constrained('tramite_adjuntos')->restrictOnDelete();
            $table->string('status', 30)->default('ACTIVO');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tramite_id', 'tipo_documento_id', 'version']);
            $table->index('persona_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_adjuntos');
    }
};
