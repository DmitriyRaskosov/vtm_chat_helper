<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->text('value');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['character_id', 'key']);
            $table->index(['character_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_traits');
    }
};
