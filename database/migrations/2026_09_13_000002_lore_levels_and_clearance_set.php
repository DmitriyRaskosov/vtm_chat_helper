<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lore_entries DROP CONSTRAINT IF EXISTS lore_entries_classification_check');
        DB::statement("UPDATE lore_entries SET classification = CASE classification WHEN 'table' THEN '0' WHEN 'initiated' THEN '2' WHEN 'st_secret' THEN '5' ELSE classification END");
        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_classification_check CHECK (classification IN ('0', '1', '2', '3', '4', '5'))");
        DB::statement("ALTER TABLE lore_entries ALTER COLUMN classification SET DEFAULT '0'");

        DB::statement('ALTER TABLE lore_entry_versions DROP CONSTRAINT IF EXISTS lore_entry_versions_classification_check');
        DB::statement("UPDATE lore_entry_versions SET classification = CASE classification WHEN 'table' THEN '0' WHEN 'initiated' THEN '2' WHEN 'st_secret' THEN '5' ELSE classification END");
        DB::statement("ALTER TABLE lore_entry_versions ADD CONSTRAINT lore_entry_versions_classification_check CHECK (classification IN ('0', '1', '2', '3', '4', '5'))");
        DB::statement("ALTER TABLE lore_entry_versions ALTER COLUMN classification SET DEFAULT '0'");

        Schema::table('lore_entries', function (Blueprint $table) {
            $table->boolean('situational')->default(false);
        });
        Schema::table('lore_entry_versions', function (Blueprint $table) {
            $table->boolean('situational')->default(false);
        });

        DB::statement("ALTER TABLE characters ADD COLUMN lore_clearance_levels smallint[] NOT NULL DEFAULT '{0}'");
        DB::statement("UPDATE characters SET lore_clearance_levels = CASE lore_clearance WHEN 'table' THEN '{0}'::smallint[] WHEN 'initiated' THEN '{0,1,2}'::smallint[] WHEN 'st_secret' THEN '{0,1,2,3,4,5}'::smallint[] ELSE '{0}'::smallint[] END");
        DB::statement('ALTER TABLE characters DROP CONSTRAINT IF EXISTS characters_lore_clearance_check');
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('lore_clearance');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('lore_clearance', 32)->default('table');
        });
        DB::statement("UPDATE characters SET lore_clearance = CASE WHEN lore_clearance_levels @> ARRAY[5]::smallint[] AND array_length(lore_clearance_levels, 1) = 6 THEN 'st_secret' WHEN lore_clearance_levels @> ARRAY[2]::smallint[] THEN 'initiated' ELSE 'table' END");
        DB::statement("ALTER TABLE characters ADD CONSTRAINT characters_lore_clearance_check CHECK (lore_clearance IN ('table', 'initiated', 'st_secret'))");
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('lore_clearance_levels');
        });

        Schema::table('lore_entry_versions', function (Blueprint $table) {
            $table->dropColumn('situational');
        });
        Schema::table('lore_entries', function (Blueprint $table) {
            $table->dropColumn('situational');
        });

        DB::statement('ALTER TABLE lore_entry_versions DROP CONSTRAINT IF EXISTS lore_entry_versions_classification_check');
        DB::statement("UPDATE lore_entry_versions SET classification = CASE classification WHEN '0' THEN 'table' WHEN '2' THEN 'initiated' WHEN '5' THEN 'st_secret' ELSE 'table' END");
        DB::statement("ALTER TABLE lore_entry_versions ADD CONSTRAINT lore_entry_versions_classification_check CHECK (classification IN ('table', 'initiated', 'st_secret'))");
        DB::statement("ALTER TABLE lore_entry_versions ALTER COLUMN classification SET DEFAULT 'table'");

        DB::statement('ALTER TABLE lore_entries DROP CONSTRAINT IF EXISTS lore_entries_classification_check');
        DB::statement("UPDATE lore_entries SET classification = CASE classification WHEN '0' THEN 'table' WHEN '2' THEN 'initiated' WHEN '5' THEN 'st_secret' ELSE 'table' END");
        DB::statement("ALTER TABLE lore_entries ADD CONSTRAINT lore_entries_classification_check CHECK (classification IN ('table', 'initiated', 'st_secret'))");
        DB::statement("ALTER TABLE lore_entries ALTER COLUMN classification SET DEFAULT 'table'");
    }
};
