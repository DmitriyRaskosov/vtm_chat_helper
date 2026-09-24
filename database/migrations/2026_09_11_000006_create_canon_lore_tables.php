<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_lore_entries', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('title');
            $table->text('text');
            $table->string('category', 64)->index();
            $table->jsonb('tags')->nullable();
            $table->string('source_book')->nullable();
            $table->smallInteger('source_page')->nullable();
            $table->string('source_url')->nullable();
            $table->date('retrieved_at')->nullable();
            $table->smallInteger('era_from')->nullable();
            $table->smallInteger('era_to')->nullable();
            $table->timestamps();

            $table->index(['category', 'slug']);
        });

        Schema::create('canon_lore_entry_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lore_entry_id')
                ->constrained('canon_lore_entries')
                ->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['lore_entry_id', 'entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_lore_chunks');
        Schema::dropIfExists('canon_lore_entry_entities');
        Schema::dropIfExists('canon_lore_entries');
    }
};
