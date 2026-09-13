<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lore_entries', function (Blueprint $table) {
            $table->string('classification', 32)->default('table');
        });
        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_classification_check CHECK (classification IN ('table', 'initiated', 'st_secret'))");

        Schema::table('lore_entry_versions', function (Blueprint $table) {
            $table->string('classification', 32)->default('table');
        });
        DB::statement("ALTER TABLE lore_entry_versions ADD CONSTRAINT lore_entry_versions_classification_check CHECK (classification IN ('table', 'initiated', 'st_secret'))");

        Schema::table('characters', function (Blueprint $table) {
            $table->string('lore_clearance', 32)->default('table');
        });
        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_lore_clearance_check CHECK (lore_clearance IN ('table', 'initiated', 'st_secret'))");

        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->string('access', 16)->default('grant');
        });
        DB::statement("ALTER TABLE character_lore_knowledge ADD CONSTRAINT character_lore_knowledge_access_check CHECK (access IN ('grant', 'deny'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE character_lore_knowledge DROP CONSTRAINT IF EXISTS character_lore_knowledge_access_check');
        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->dropColumn('access');
        });

        DB::statement('ALTER TABLE characters DROP CONSTRAINT IF EXISTS characters_lore_clearance_check');
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('lore_clearance');
        });

        DB::statement('ALTER TABLE lore_entry_versions DROP CONSTRAINT IF EXISTS lore_entry_versions_classification_check');
        Schema::table('lore_entry_versions', function (Blueprint $table) {
            $table->dropColumn('classification');
        });

        DB::statement('ALTER TABLE lore_entries DROP CONSTRAINT IF EXISTS lore_entries_classification_check');
        Schema::table('lore_entries', function (Blueprint $table) {
            $table->dropColumn('classification');
        });
    }
};
