<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lore_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('title', 200);
            $table->string('kind', 32);
            $table->text('canonical_text');
            $table->string('status', 16)->default('draft');
            $table->string('visibility', 32)->default('public');
            $table->unsignedInteger('current_version');
            $table->string('legacy_source_id', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'lore_entries_id_chronicle_unique');
            $table->index(['chronicle_id', 'kind'], 'lore_entries_chronicle_kind_index');
            $table->unique(['chronicle_id', 'legacy_source_id'], 'lore_entries_chronicle_legacy_unique');
        });

        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_kind_check CHECK (kind IN ('history', 'place', 'faction', 'person', 'ritual', 'item', 'custom'))");
        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_status_check CHECK (status IN ('draft', 'approved'))");
        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_visibility_check CHECK (visibility IN ('public', 'storyteller_only'))");

        Schema::table('lore_entries', function (Blueprint $table) {
            $table->foreign('chronicle_id')->references('id')->on('chronicles')->restrictOnDelete();
        });

        Schema::create('lore_entry_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lore_entry_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedInteger('version');
            $table->string('title', 200);
            $table->string('kind', 32);
            $table->text('canonical_text');
            $table->string('status', 16);
            $table->string('visibility', 32);
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['lore_entry_id', 'version'], 'lore_entry_versions_entry_version_unique');
        });

        Schema::table('lore_entry_versions', function (Blueprint $table) {
            $table->foreign(['lore_entry_id', 'chronicle_id'], 'lore_entry_versions_entry_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('lore_entries')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_lore_entry_version_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'lore_entry_versions is immutable';
END;
$$;

DROP TRIGGER IF EXISTS prevent_lore_entry_version_mutation ON lore_entry_versions;
CREATE TRIGGER prevent_lore_entry_version_mutation
    BEFORE UPDATE OR DELETE ON lore_entry_versions
    FOR EACH ROW
    EXECUTE FUNCTION prevent_lore_entry_version_mutation();
SQL);

        Schema::create('lore_entry_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lore_entry_id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->string('role', 64)->nullable();
            $table->timestamps();

            $table->unique(['lore_entry_id', 'entity_id'], 'lore_entry_entities_entry_entity_unique');
        });

        Schema::table('lore_entry_entities', function (Blueprint $table) {
            $table->foreign(['lore_entry_id', 'chronicle_id'], 'lore_entry_entities_entry_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('lore_entries')
                ->restrictOnDelete();
            $table->foreign(['entity_id', 'chronicle_id'], 'lore_entry_entities_entity_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS prevent_lore_entry_version_mutation ON lore_entry_versions;
DROP FUNCTION IF EXISTS prevent_lore_entry_version_mutation();
SQL);

        Schema::dropIfExists('lore_entry_entities');
        Schema::dropIfExists('lore_entry_versions');
        Schema::dropIfExists('lore_entries');
    }
};
