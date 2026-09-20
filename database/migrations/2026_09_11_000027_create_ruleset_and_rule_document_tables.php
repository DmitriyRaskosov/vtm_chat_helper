<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rulesets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('edition', 64);
            $table->string('language', 16);
            $table->string('status', 16)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'edition', 'language'], 'rulesets_name_edition_language_unique');
        });

        DB::statement("ALTER TABLE rulesets ADD CONSTRAINT rulesets_status_check CHECK (status IN ('draft', 'published', 'archived'))");

        Schema::create('rule_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleset_id')->constrained('rulesets')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('section', 160);
            $table->text('canonical_text');
            $table->string('source_reference', 200)->nullable();
            $table->unsignedInteger('current_version');
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ruleset_id', 'section'], 'rule_documents_ruleset_section_index');
        });

        DB::statement("ALTER TABLE rule_documents ADD CONSTRAINT rule_documents_status_check CHECK (status IN ('draft', 'approved'))");

        Schema::create('rule_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 200);
            $table->string('section', 160);
            $table->text('canonical_text');
            $table->string('source_reference', 200)->nullable();
            $table->string('status', 16);
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['rule_document_id', 'version'], 'rule_document_versions_document_version_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_rule_document_version_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'rule_document_versions is immutable';
END;
$$;

DROP TRIGGER IF EXISTS prevent_rule_document_version_mutation ON rule_document_versions;
CREATE TRIGGER prevent_rule_document_version_mutation
    BEFORE UPDATE OR DELETE ON rule_document_versions
    FOR EACH ROW
    EXECUTE FUNCTION prevent_rule_document_version_mutation();
SQL);

        Schema::create('rule_document_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->foreignId('discipline_id')->constrained('canon_disciplines')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['rule_document_id', 'discipline_id'], 'rule_document_disciplines_unique');
        });

        Schema::create('rule_document_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->foreignId('discipline_power_id')->constrained('canon_discipline_powers')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['rule_document_id', 'discipline_power_id'], 'rule_document_powers_unique');
        });

        Schema::create('rule_document_stat_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->string('stat_category', 32);
            $table->string('stat_key', 64);
            $table->timestamps();

            $table->unique(['rule_document_id', 'stat_category', 'stat_key'], 'rule_document_stat_keys_unique');
        });

        DB::statement("ALTER TABLE rule_document_stat_keys ADD CONSTRAINT rule_document_stat_keys_category_check CHECK (stat_category IN ('attribute', 'ability', 'background', 'virtue', 'other'))");

        Schema::create('rule_document_effect_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->string('effect_type', 32);
            $table->timestamps();

            $table->unique(['rule_document_id', 'effect_type'], 'rule_document_effect_types_unique');
        });

        DB::statement("ALTER TABLE rule_document_effect_types ADD CONSTRAINT rule_document_effect_types_check CHECK (effect_type IN ('penalty', 'bonus', 'wound', 'temporary'))");

        Schema::create('chronicle_rule_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chronicle_id');
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->text('override_text');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('current_version');
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['chronicle_id', 'rule_document_id'], 'chronicle_rule_overrides_chronicle_document_unique');
        });

        DB::statement("ALTER TABLE chronicle_rule_overrides ADD CONSTRAINT chronicle_rule_overrides_status_check CHECK (status IN ('draft', 'approved'))");

        Schema::table('chronicle_rule_overrides', function (Blueprint $table) {
            $table->foreign('chronicle_id')->references('id')->on('chronicles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS prevent_rule_document_version_mutation ON rule_document_versions;
DROP FUNCTION IF EXISTS prevent_rule_document_version_mutation();
SQL);

        Schema::dropIfExists('chronicle_rule_overrides');
        Schema::dropIfExists('rule_document_effect_types');
        Schema::dropIfExists('rule_document_stat_keys');
        Schema::dropIfExists('rule_document_powers');
        Schema::dropIfExists('rule_document_disciplines');
        Schema::dropIfExists('rule_document_versions');
        Schema::dropIfExists('rule_documents');
        Schema::dropIfExists('rulesets');
    }
};
