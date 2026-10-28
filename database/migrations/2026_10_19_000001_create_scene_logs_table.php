<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('visibility', 16);
            $table->text('message');
            $table->jsonb('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scene_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_logs');
    }
};
