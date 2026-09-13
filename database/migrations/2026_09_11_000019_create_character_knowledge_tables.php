<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_lore_knowledge', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('lore_entry_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->string('knowledge_level', 16);
            $table->unsignedTinyInteger('confidence')->default(3);
            $table->timestamp('learned_at');
            $table->unsignedBigInteger('source_world_event_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'lore_entry_id'], 'character_lore_knowledge_character_entry_unique');
        });

        DB::statement("ALTER TABLE character_lore_knowledge ADD CONSTRAINT character_lore_knowledge_level_check CHECK (knowledge_level IN ('rumor', 'partial', 'known', 'expert'))");
        DB::statement('ALTER TABLE character_lore_knowledge ADD CONSTRAINT character_lore_knowledge_confidence_check CHECK (confidence BETWEEN 0 AND 5)');

        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->foreign(['character_id', 'chronicle_id'], 'character_lore_knowledge_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
            $table->foreign(['lore_entry_id', 'chronicle_id'], 'character_lore_knowledge_entry_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('lore_entries')
                ->restrictOnDelete();
            $table->foreign('source_world_event_id')->references('id')->on('world_events')->restrictOnDelete();
        });

        Schema::create('character_rule_knowledge', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->string('knowledge_level', 16);
            $table->unsignedTinyInteger('confidence')->default(3);
            $table->timestamp('learned_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'rule_document_id'], 'character_rule_knowledge_character_document_unique');
        });

        DB::statement("ALTER TABLE character_rule_knowledge ADD CONSTRAINT character_rule_knowledge_level_check CHECK (knowledge_level IN ('rumor', 'partial', 'known', 'expert'))");
        DB::statement('ALTER TABLE character_rule_knowledge ADD CONSTRAINT character_rule_knowledge_confidence_check CHECK (confidence BETWEEN 0 AND 5)');

        Schema::table('character_rule_knowledge', function (Blueprint $table) {
            $table->foreign('character_id')->references('id')->on('characters')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_rule_knowledge');
        Schema::dropIfExists('character_lore_knowledge');
    }
};
