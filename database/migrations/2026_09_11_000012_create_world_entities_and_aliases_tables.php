<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_id')->constrained()->restrictOnDelete();
            $table->string('entity_type', 20);
            $table->string('canonical_name', 120);
            $table->string('slug', 160);
            $table->text('short_description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'world_entities_id_chronicle_unique');
            $table->unique(['chronicle_id', 'slug'], 'world_entities_chronicle_slug_unique');
            $table->index(['chronicle_id', 'entity_type'], 'world_entities_chronicle_type_index');
        });

        Schema::create('world_entity_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->string('alias', 120);
            $table->string('normalized_alias', 120);
            $table->string('alias_type', 20);
            $table->string('language', 16)->nullable();
            $table->timestamps();

            $table->foreign(['entity_id', 'chronicle_id'], 'world_entity_aliases_entity_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->unique(['chronicle_id', 'normalized_alias'], 'world_entity_aliases_chronicle_normalized_unique');
        });

        DB::statement(
            "CREATE UNIQUE INDEX world_entity_aliases_one_canonical
             ON world_entity_aliases (entity_id)
             WHERE alias_type = 'canonical'"
        );

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_world_entity_delete()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'World entities cannot be deleted; archive them instead.';
END;
$$;

DROP TRIGGER IF EXISTS prevent_world_entity_delete ON world_entities;
CREATE TRIGGER prevent_world_entity_delete
    BEFORE DELETE ON world_entities
    FOR EACH ROW
    EXECUTE FUNCTION prevent_world_entity_delete();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS prevent_world_entity_delete ON world_entities;
DROP FUNCTION IF EXISTS prevent_world_entity_delete();
SQL);

        Schema::dropIfExists('world_entity_aliases');
        Schema::dropIfExists('world_entities');
    }
};
