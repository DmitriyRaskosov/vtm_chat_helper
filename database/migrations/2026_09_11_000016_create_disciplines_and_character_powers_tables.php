<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('ruleset', 64);
            $table->string('key', 64);
            $table->string('display_name', 120);
            $table->timestamps();

            $table->unique(['ruleset', 'key'], 'disciplines_ruleset_key_unique');
        });

        Schema::create('discipline_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->string('key', 64);
            $table->string('display_name', 120);
            $table->unsignedTinyInteger('required_level');
            $table->string('rule_key', 80)->nullable();
            $table->timestamps();

            $table->unique(['discipline_id', 'key'], 'discipline_powers_discipline_key_unique');
            $table->unique(['id', 'discipline_id'], 'discipline_powers_id_discipline_unique');
        });

        DB::statement('ALTER TABLE discipline_powers ADD CONSTRAINT discipline_powers_required_level_check CHECK (required_level BETWEEN 1 AND 9)');

        Schema::create('character_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['character_id', 'discipline_id'], 'character_disciplines_character_discipline_unique');
        });

        DB::statement('ALTER TABLE character_disciplines ADD CONSTRAINT character_disciplines_level_check CHECK (level BETWEEN 1 AND 9)');

        Schema::create('character_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->unsignedBigInteger('discipline_id');
            $table->unsignedBigInteger('discipline_power_id');
            $table->timestamp('acquired_at')->nullable();
            $table->text('note')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'discipline_power_id'], 'character_powers_character_power_unique');
        });

        Schema::table('character_powers', function (Blueprint $table) {
            $table->foreign(['character_id', 'discipline_id'], 'character_powers_character_discipline_foreign')
                ->references(['character_id', 'discipline_id'])
                ->on('character_disciplines')
                ->restrictOnDelete();
            $table->foreign(['discipline_power_id', 'discipline_id'], 'character_powers_power_discipline_foreign')
                ->references(['id', 'discipline_id'])
                ->on('discipline_powers')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_character_power_level()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    known_level integer;
    needed_level integer;
BEGIN
    SELECT required_level INTO needed_level
    FROM discipline_powers
    WHERE id = NEW.discipline_power_id;

    SELECT level INTO known_level
    FROM character_disciplines
    WHERE character_id = NEW.character_id
      AND discipline_id = NEW.discipline_id;

    IF known_level IS NULL OR needed_level IS NULL OR known_level < needed_level THEN
        RAISE EXCEPTION 'Character discipline level is below the power required level.';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS enforce_character_power_level ON character_powers;
CREATE TRIGGER enforce_character_power_level
    BEFORE INSERT OR UPDATE OF character_id, discipline_id, discipline_power_id
    ON character_powers
    FOR EACH ROW
    EXECUTE FUNCTION enforce_character_power_level();

CREATE OR REPLACE FUNCTION enforce_character_discipline_power_level()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM character_powers
        JOIN discipline_powers ON discipline_powers.id = character_powers.discipline_power_id
        WHERE character_powers.character_id = NEW.character_id
          AND character_powers.discipline_id = NEW.discipline_id
          AND discipline_powers.required_level > NEW.level
    ) THEN
        RAISE EXCEPTION 'Character discipline level is below a learned power required level.';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS enforce_character_discipline_power_level ON character_disciplines;
CREATE TRIGGER enforce_character_discipline_power_level
    BEFORE UPDATE OF level
    ON character_disciplines
    FOR EACH ROW
    EXECUTE FUNCTION enforce_character_discipline_power_level();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS enforce_character_discipline_power_level ON character_disciplines;
DROP FUNCTION IF EXISTS enforce_character_discipline_power_level();
DROP TRIGGER IF EXISTS enforce_character_power_level ON character_powers;
DROP FUNCTION IF EXISTS enforce_character_power_level();
SQL);

        Schema::dropIfExists('character_powers');
        Schema::dropIfExists('character_disciplines');
        Schema::dropIfExists('discipline_powers');
        Schema::dropIfExists('disciplines');
    }
};
