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

        Schema::create('world_events', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->string('entity_type', 20)->default('event');
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type', 32);
            $table->string('status', 32)->default('proposed');
            $table->unsignedTinyInteger('importance')->default(1);
            $table->string('visibility', 32)->default('public');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'world_events_id_chronicle_unique');
            $table->unique(['id', 'entity_type'], 'world_events_id_type_unique');
        });

        DB::statement("ALTER TABLE world_events ADD CONSTRAINT world_events_entity_type_check CHECK (entity_type = 'event')");
        DB::statement("ALTER TABLE world_events ADD CONSTRAINT world_events_type_check CHECK (event_type IN ('social', 'violence', 'discovery', 'ritual', 'political', 'other'))");
        DB::statement("ALTER TABLE world_events ADD CONSTRAINT world_events_status_check CHECK (status IN ('proposed', 'canonical', 'rejected'))");
        DB::statement("ALTER TABLE world_events ADD CONSTRAINT world_events_visibility_check CHECK (visibility IN ('public', 'storyteller_only'))");
        DB::statement('ALTER TABLE world_events ADD CONSTRAINT world_events_importance_check CHECK (importance <= 5)');

        Schema::table('world_events', function (Blueprint $table) {
            $table->foreign(['id', 'chronicle_id'], 'world_events_id_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['id', 'entity_type'], 'world_events_id_type_foreign')
                ->references(['id', 'entity_type'])
                ->on('world_entities')
                ->restrictOnDelete();
        });

        Schema::create('world_event_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('entity_id');
            $table->string('participant_role', 32);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'entity_id', 'participant_role'], 'world_event_participants_event_entity_role_unique');
        });

        DB::statement("ALTER TABLE world_event_participants ADD CONSTRAINT world_event_participants_role_check CHECK (participant_role IN ('actor', 'victim', 'witness', 'organizer', 'mentioned', 'other'))");

        Schema::table('world_event_participants', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('world_events')->restrictOnDelete();
            $table->foreign('entity_id')->references('id')->on('world_entities')->restrictOnDelete();
        });

        Schema::create('world_event_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->restrictOnDelete();
            $table->foreignId('copilot_request_id')->nullable()->constrained('copilot_requests')->restrictOnDelete();
            $table->text('excerpt')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE world_event_sources ADD CONSTRAINT world_event_sources_reference_check CHECK (message_id IS NOT NULL OR scene_id IS NOT NULL OR copilot_request_id IS NOT NULL)');

        Schema::table('world_event_sources', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('world_events')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_event_sources');
        Schema::dropIfExists('world_event_participants');
        Schema::dropIfExists('world_events');
    }
};
