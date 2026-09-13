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
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('clan_entity_id')->nullable();
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

        Schema::table('characters', function (Blueprint $table) {
            $table->foreign(['id', 'chronicle_id'], 'characters_id_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['id', 'entity_type'], 'characters_id_type_foreign')
                ->references(['id', 'entity_type'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['clan_entity_id', 'chronicle_id'], 'characters_clan_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['sire_character_id', 'chronicle_id'], 'characters_sire_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('author_character_id')
                ->nullable()
                ->after('npc_name')
                ->constrained('characters')
                ->restrictOnDelete();
        });

        Schema::table('copilot_requests', function (Blueprint $table) {
            $table->foreignId('character_id')
                ->nullable()
                ->after('npc_name')
                ->constrained('characters')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('copilot_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('character_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_character_id');
        });

        Schema::dropIfExists('characters');
    }
};
