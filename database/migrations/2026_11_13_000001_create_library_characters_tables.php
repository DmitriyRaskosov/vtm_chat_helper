<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('character_type', 20);
            $table->foreignId('clan_id')->nullable()->constrained('canon_clans')->nullOnDelete();
            $table->foreignId('sect_id')->nullable()->constrained('canon_sects')->nullOnDelete();
            $table->string('nature', 64)->nullable();
            $table->string('demeanor', 64)->nullable();
            $table->string('concept', 120)->nullable();
            $table->unsignedTinyInteger('generation')->nullable();
            $table->jsonb('stats')->nullable();
            $table->jsonb('sheet_data')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE library_characters ADD CONSTRAINT library_characters_type_check CHECK (character_type IN ('player', 'npc'))");

        Schema::create('library_character_biographies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_character_id')->constrained('library_characters')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->text('full_text')->nullable();
            $table->text('principles')->nullable();
            $table->text('motivation')->nullable();
            $table->text('fears')->nullable();
            $table->text('desires')->nullable();
            $table->text('behavioral_rules')->nullable();
            $table->timestamps();

            $table->unique('library_character_id');
        });

        Schema::create('library_character_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_character_id')->constrained('library_characters')->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained('canon_disciplines')->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(
                ['library_character_id', 'discipline_id'],
                'library_character_disciplines_library_character_discipline_unique',
            );
        });

        DB::statement('ALTER TABLE library_character_disciplines ADD CONSTRAINT library_character_disciplines_level_check CHECK (level BETWEEN 1 AND 9)');

        Schema::create('library_character_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_character_id')->constrained('library_characters')->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained('canon_disciplines')->restrictOnDelete();
            $table->foreignId('discipline_power_id')->constrained('canon_discipline_powers')->restrictOnDelete();
            $table->timestamp('acquired_at')->nullable();
            $table->text('note')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->timestamps();

            $table->unique(
                ['library_character_id', 'discipline_power_id'],
                'library_character_powers_library_character_power_unique',
            );
        });

        Schema::create('library_character_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_character_id')->constrained('library_characters')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->boolean('is_equipped')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_character_items');
        Schema::dropIfExists('library_character_powers');
        Schema::dropIfExists('library_character_disciplines');
        Schema::dropIfExists('library_character_biographies');
        Schema::dropIfExists('library_characters');
    }
};
