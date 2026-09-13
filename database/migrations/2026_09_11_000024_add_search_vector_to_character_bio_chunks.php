<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE character_bio_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', content)) STORED");
        DB::statement('CREATE INDEX character_bio_chunks_search_vector_gin ON character_bio_chunks USING gin (search_vector)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS character_bio_chunks_search_vector_gin');
        DB::statement('ALTER TABLE character_bio_chunks DROP COLUMN IF EXISTS search_vector');
    }
};
