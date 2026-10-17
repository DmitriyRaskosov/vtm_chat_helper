<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('character');
            $table->string('character_type', 20);
            $table->foreignId('clan_id')->nullable()->after('character_type')->constrained('canon_clans')->nullOnDelete();
            $table->foreignId('sect_id')->nullable()
                ->after('clan_id')
                ->constrained('canon_sects')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sire_character_id')->nullable();
            $table->unsignedTinyInteger('generation')->nullable();
            $table->unsignedSmallInteger('apparent_age')->nullable();
            $table->unsignedSmallInteger('actual_age')->nullable();
            $table->string('nature', 64)->nullable();
            $table->string('demeanor', 64)->nullable();
            $table->string('concept', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'characters_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'characters_id_type_unique');

            $table->foreign(['id', 'chronicle_id'], 'characters_id_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['id', 'entity_type'], 'characters_id_type_foreign')
                ->references(['id', 'entity_type'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['sire_character_id', 'chronicle_id'], 'characters_sire_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_entity_type_check CHECK (entity_type = 'character')");
        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_type_check CHECK (character_type IN ('player', 'npc'))");
        DB::statement(<<<'SQL'
ALTER TABLE characters ADD CONSTRAINT characters_player_user_check CHECK (
    (character_type = 'player' AND user_id IS NOT NULL)
    OR (character_type = 'npc' AND user_id IS NULL)
)
SQL);
        DB::statement('ALTER TABLE characters ADD CONSTRAINT characters_sire_not_self_check CHECK (sire_character_id IS NULL OR sire_character_id <> id)');

        DB::statement('ALTER TABLE messages ADD CONSTRAINT messages_author_character_id_foreign FOREIGN KEY (author_character_id) REFERENCES characters (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE copilot_requests ADD CONSTRAINT copilot_requests_character_id_foreign FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE copilot_requests DROP CONSTRAINT IF EXISTS copilot_requests_character_id_foreign');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_author_character_id_foreign');

        Schema::dropIfExists('characters');
    }
};
