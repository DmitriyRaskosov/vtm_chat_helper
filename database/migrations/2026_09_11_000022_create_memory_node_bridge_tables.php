<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_node_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_node_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->string('role', 32)->default('subject');
            $table->timestamps();

            $table->unique(['memory_node_id', 'entity_id'], 'memory_node_entities_node_entity_unique');
        });

        DB::statement("ALTER TABLE memory_node_entities ADD CONSTRAINT memory_node_entities_role_check CHECK (role IN ('subject', 'mentioned', 'place', 'opponent', 'witness', 'other'))");

        Schema::table('memory_node_entities', function (Blueprint $table) {
            $table->foreign(['memory_node_id', 'character_id'], 'memory_node_entities_node_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
            $table->foreign(['entity_id', 'chronicle_id'], 'memory_node_entities_entity_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['character_id', 'chronicle_id'], 'memory_node_entities_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        Schema::create('memory_node_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_node_id');
            $table->unsignedBigInteger('character_id');
            $table->foreignId('message_id')->constrained('messages')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['memory_node_id', 'message_id'], 'memory_node_messages_node_message_unique');
        });

        Schema::table('memory_node_messages', function (Blueprint $table) {
            $table->foreign(['memory_node_id', 'character_id'], 'memory_node_messages_node_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
        });

        Schema::create('memory_node_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_node_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->timestamps();

            $table->unique(['memory_node_id', 'event_id'], 'memory_node_events_node_event_unique');
        });

        Schema::table('memory_node_events', function (Blueprint $table) {
            $table->foreign(['memory_node_id', 'character_id'], 'memory_node_events_node_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
            $table->foreign(['event_id', 'chronicle_id'], 'memory_node_events_event_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_events')
                ->restrictOnDelete();
            $table->foreign(['character_id', 'chronicle_id'], 'memory_node_events_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        Schema::create('memory_node_lore_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_node_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('lore_entry_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->timestamps();

            $table->unique(['memory_node_id', 'lore_entry_id'], 'memory_node_lore_entries_node_lore_unique');
        });

        Schema::table('memory_node_lore_entries', function (Blueprint $table) {
            $table->foreign(['memory_node_id', 'character_id'], 'memory_node_lore_entries_node_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
            $table->foreign(['lore_entry_id', 'chronicle_id'], 'memory_node_lore_entries_lore_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('lore_entries')
                ->restrictOnDelete();
            $table->foreign(['character_id', 'chronicle_id'], 'memory_node_lore_entries_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        Schema::create('memory_node_scenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_node_id');
            $table->unsignedBigInteger('character_id');
            $table->foreignId('scene_id')->constrained('scenes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['memory_node_id', 'scene_id'], 'memory_node_scenes_node_scene_unique');
        });

        Schema::table('memory_node_scenes', function (Blueprint $table) {
            $table->foreign(['memory_node_id', 'character_id'], 'memory_node_scenes_node_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_node_scenes');
        Schema::dropIfExists('memory_node_lore_entries');
        Schema::dropIfExists('memory_node_events');
        Schema::dropIfExists('memory_node_messages');
        Schema::dropIfExists('memory_node_entities');
    }
};
