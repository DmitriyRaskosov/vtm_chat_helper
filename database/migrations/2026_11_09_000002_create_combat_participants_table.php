<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combat_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->integer('initiative')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['combat_encounter_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_participants');
    }
};
