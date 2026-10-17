<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('location');
            $table->unsignedBigInteger('parent_location_id')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'locations_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'locations_id_type_unique');

            $table->foreign(['id', 'chronicle_id'], 'locations_id_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['id', 'entity_type'], 'locations_id_type_foreign')
                ->references(['id', 'entity_type'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['parent_location_id', 'chronicle_id'], 'locations_parent_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('locations')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_entity_type_check CHECK (entity_type = 'location')");
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
