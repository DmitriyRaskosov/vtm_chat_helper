<?php

use App\World\WorldRelationTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (WorldRelationTypeCatalog::definitions() as $definition) {
            if (DB::table('world_relation_types')->where('key', $definition['key'])->exists()) {
                continue;
            }

            DB::table('world_relation_types')->insert([
                'key' => $definition['key'],
                'display_name' => $definition['display_name'],
                'allowed_source_types' => json_encode($definition['allowed_source_types']),
                'allowed_target_types' => json_encode($definition['allowed_target_types']),
                'symmetric' => $definition['symmetric'],
                'transitive' => $definition['transitive'],
                'default_weight' => $definition['default_weight'],
                'enabled' => $definition['enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('character_affiliations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('target_entity_id');
            $table->string('affiliation_type', 32);
            $table->string('stance', 32);
            $table->unsignedTinyInteger('trust')->default(0);
            $table->unsignedTinyInteger('loyalty')->default(0);
            $table->unsignedTinyInteger('fear')->default(0);
            $table->unsignedTinyInteger('obligation')->default(0);
            $table->string('role', 120)->nullable();
            $table->string('rank', 120)->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'character_affiliations_id_chronicle_unique');
            $table->unique(['id', 'character_id', 'target_entity_id'], 'character_affiliations_id_endpoints_unique');
            $table->index(['character_id', 'affiliation_type'], 'character_affiliations_character_type_index');
        });

        DB::statement("ALTER TABLE character_affiliations ADD CONSTRAINT character_affiliations_type_check CHECK (affiliation_type IN ('member', 'resident', 'owner', 'adherent', 'other'))");
        DB::statement("ALTER TABLE character_affiliations ADD CONSTRAINT character_affiliations_stance_check CHECK (stance IN ('allied', 'neutral', 'wary', 'hostile'))");
        DB::statement('ALTER TABLE character_affiliations ADD CONSTRAINT character_affiliations_metrics_check CHECK (trust <= 5 AND loyalty <= 5 AND fear <= 5 AND obligation <= 5)');

        Schema::table('character_affiliations', function (Blueprint $table) {
            $table->foreign(['id', 'chronicle_id'], 'character_affiliations_relation_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_relations')
                ->restrictOnDelete();
            $table->foreign(['id', 'character_id', 'target_entity_id'], 'character_affiliations_relation_endpoints_foreign')
                ->references(['id', 'source_entity_id', 'target_entity_id'])
                ->on('world_relations')
                ->restrictOnDelete();
            $table->foreign(['character_id', 'chronicle_id'], 'character_affiliations_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
            $table->foreign(['target_entity_id', 'chronicle_id'], 'character_affiliations_target_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
        });

        Schema::create('character_affiliation_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliation_id');
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->string('field', 64);
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['affiliation_id', 'created_at'], 'character_affiliation_changes_affiliation_created_index');
        });

        Schema::table('character_affiliation_changes', function (Blueprint $table) {
            $table->foreign('affiliation_id')->references('id')->on('character_affiliations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_affiliation_changes');
        Schema::dropIfExists('character_affiliations');
    }
};
