<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->unique(['user_id', 'scene_id'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
