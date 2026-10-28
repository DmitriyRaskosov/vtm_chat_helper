<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_clans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->text('name');
            $table->string('nickname', 128);
            $table->text('description');
            $table->text('weakness');
            $table->text('weakness_system');
            $table->foreignId('parent_clan_id')
                ->nullable()
                ->constrained('canon_clans')
                ->restrictOnDelete();
            $table->boolean('is_bloodline')->default(false);
            $table->boolean('is_playable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_clans');
    }
};
