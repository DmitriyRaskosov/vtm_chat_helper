<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->string('category', 32);
            $table->string('stat_key', 64);
            $table->string('display_name', 120);
            $table->unsignedSmallInteger('value');
            $table->unsignedSmallInteger('maximum')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'category', 'stat_key'], 'character_stats_character_category_key_unique');
            $table->index(['character_id', 'category'], 'character_stats_character_category_index');
        });

        DB::statement("ALTER TABLE character_stats ADD CONSTRAINT character_stats_category_check CHECK (category IN ('attribute', 'ability', 'background', 'virtue', 'other'))");
        DB::statement('ALTER TABLE character_stats ADD CONSTRAINT character_stats_value_maximum_check CHECK (maximum IS NULL OR value <= maximum)');

        Schema::create('character_stat_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_stat_id')->constrained('character_stats')->restrictOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['character_stat_id', 'name'], 'character_stat_specializations_stat_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_stat_specializations');
        Schema::dropIfExists('character_stats');
    }
};
