<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_merits_flaws', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('kind');
            $table->text('category');
            $table->integer('cost');
            $table->text('description');
            $table->text('system');
            $table->boolean('has_levels');
            $table->text('source_book');
        });

        DB::statement("ALTER TABLE canon_merits_flaws ADD CONSTRAINT canon_merits_flaws_kind_check CHECK (kind IN ('merit', 'flaw'))");
        DB::statement("ALTER TABLE canon_merits_flaws ADD CONSTRAINT canon_merits_flaws_category_check CHECK (category IN ('physical', 'mental', 'social', 'supernatural'))");

        Schema::create('canon_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('description');
            $table->integer('max_rating')->default(5);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_backgrounds');
        Schema::dropIfExists('canon_merits_flaws');
    }
};
