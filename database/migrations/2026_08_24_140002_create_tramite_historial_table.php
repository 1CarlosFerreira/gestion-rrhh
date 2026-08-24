<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action_code', 100);
            $table->foreignId('from_estado_id')->nullable()->constrained('estados_tramite')->restrictOnDelete();
            $table->foreignId('to_estado_id')->nullable()->constrained('estados_tramite')->restrictOnDelete();
            $table->text('observation')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tramite_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_historial');
    }
};
