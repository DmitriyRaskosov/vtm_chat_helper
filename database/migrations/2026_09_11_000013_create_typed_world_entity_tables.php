<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_entities', function (Blueprint $table) {
            $table->unique(['id', 'entity_type'], 'world_entities_id_type_unique');
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('location');
            $table->unsignedBigInteger('parent_location_id')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'locations_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'locations_id_type_unique');
        });

        Schema::create('factions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('faction');
            $table->unsignedBigInteger('parent_faction_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'factions_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'factions_id_type_unique');
        });

        Schema::create('concepts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('concept');
            $table->text('definition')->nullable();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'concepts_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'concepts_id_type_unique');
        });

        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_entity_type_check CHECK (entity_type = 'location')");
        DB::statement("ALTER TABLE factions ADD CONSTRAINT factions_entity_type_check CHECK (entity_type = 'faction')");
        DB::statement("ALTER TABLE concepts ADD CONSTRAINT concepts_entity_type_check CHECK (entity_type = 'concept')");

        foreach (['locations', 'factions', 'concepts'] as $table) {
            $this->typedIdentityForeign($table);
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->foreign(['parent_location_id', 'chronicle_id'], 'locations_parent_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('locations')
                ->restrictOnDelete();
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->foreign(['parent_faction_id', 'chronicle_id'], 'factions_parent_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('factions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('factions');
        Schema::dropIfExists('locations');

        Schema::table('world_entities', function (Blueprint $table) {
            $table->dropUnique('world_entities_id_type_unique');
        });
    }

    private function typedIdentityForeign(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->foreign(['id', 'chronicle_id'], $table.'_id_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $blueprint->foreign(['id', 'entity_type'], $table.'_id_type_foreign')
                ->references(['id', 'entity_type'])
                ->on('world_entities')
                ->restrictOnDelete();
        });
    }
};