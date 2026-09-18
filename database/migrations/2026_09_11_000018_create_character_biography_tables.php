<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_biographies', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->primary();
            $table->text('summary')->nullable();
            $table->text('full_text')->nullable();
            $table->text('principles')->nullable();
            $table->text('motivation')->nullable();
            $table->text('fears')->nullable();
            $table->text('desires')->nullable();
            $table->text('behavioral_rules')->nullable();
            $table->unsignedInteger('current_version');
            $table->string('status', 16)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE character_biographies ADD CONSTRAINT character_biographies_status_check CHECK (status IN ('draft', 'approved'))");
        DB::statement('ALTER TABLE character_biographies ADD CONSTRAINT character_biographies_body_check CHECK (summary IS NOT NULL OR full_text IS NOT NULL)');

        Schema::table('character_biographies', function (Blueprint $table) {
            $table->foreign('character_id')->references('id')->on('characters')->restrictOnDelete();
        });

        Schema::create('character_biography_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('summary')->nullable();
            $table->text('full_text')->nullable();
            $table->text('principles')->nullable();
            $table->text('motivation')->nullable();
            $table->text('fears')->nullable();
            $table->text('desires')->nullable();
            $table->text('behavioral_rules')->nullable();
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['character_id', 'version'], 'character_biography_versions_character_version_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_biography_version_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'character_biography_versions is immutable';
END;
$$;

DROP TRIGGER IF EXISTS prevent_biography_version_mutation ON character_biography_versions;
CREATE TRIGGER prevent_biography_version_mutation
    BEFORE UPDATE OR DELETE ON character_biography_versions
    FOR EACH ROW
    EXECUTE FUNCTION prevent_biography_version_mutation();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS prevent_biography_version_mutation ON character_biography_versions;
DROP FUNCTION IF EXISTS prevent_biography_version_mutation();
SQL);

        Schema::dropIfExists('character_biography_versions');
        Schema::dropIfExists('character_biographies');
    }
};
