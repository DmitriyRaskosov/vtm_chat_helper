<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedBigInteger('domitor_character_id')->nullable()->after('sire_character_id');
            $table->index('domitor_character_id', 'characters_domitor_index');
        });

        DB::statement('ALTER TABLE characters DROP CONSTRAINT characters_type_check');
        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_type_check CHECK (character_type IN ('player', 'npc', 'ghoul'))");

        DB::statement('ALTER TABLE characters DROP CONSTRAINT characters_player_user_check');
        DB::statement(<<<'SQL'
ALTER TABLE characters ADD CONSTRAINT characters_player_user_check CHECK (
    (character_type = 'player' AND user_id IS NOT NULL AND domitor_character_id IS NULL)
    OR (character_type = 'npc' AND user_id IS NULL AND domitor_character_id IS NULL)
    OR (
        character_type = 'ghoul'
        AND user_id IS NULL
        AND domitor_character_id IS NOT NULL
        AND domitor_character_id <> id
    )
)
SQL);

        Schema::table('characters', function (Blueprint $table) {
            $table->foreign(['domitor_character_id', 'chronicle_id'], 'characters_domitor_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_ghoul_domitor_type()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    domitor_type text;
BEGIN
    IF NEW.character_type = 'ghoul' THEN
        SELECT character_type INTO domitor_type
        FROM characters
        WHERE id = NEW.domitor_character_id;

        IF domitor_type IS NULL OR domitor_type = 'ghoul' THEN
            RAISE EXCEPTION 'A ghoul domitor must be a player or NPC character.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS enforce_ghoul_domitor_type ON characters;
CREATE TRIGGER enforce_ghoul_domitor_type
    BEFORE INSERT OR UPDATE OF character_type, domitor_character_id
    ON characters
    FOR EACH ROW
    EXECUTE FUNCTION enforce_ghoul_domitor_type();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS enforce_ghoul_domitor_type ON characters;
DROP FUNCTION IF EXISTS enforce_ghoul_domitor_type();
SQL);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign('characters_domitor_chronicle_foreign');
            $table->dropIndex('characters_domitor_index');
            $table->dropColumn('domitor_character_id');
        });

        DB::statement('ALTER TABLE characters DROP CONSTRAINT characters_type_check');
        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_type_check CHECK (character_type IN ('player', 'npc'))");

        DB::statement('ALTER TABLE characters DROP CONSTRAINT characters_player_user_check');
        DB::statement(<<<'SQL'
ALTER TABLE characters ADD CONSTRAINT characters_player_user_check CHECK (
    (character_type = 'player' AND user_id IS NOT NULL)
    OR (character_type = 'npc' AND user_id IS NULL)
)
SQL);
    }
};
