<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_generations', function (Blueprint $table) {
            $table->integer('generation')->primary();
            $table->integer('max_blood_pool');
            $table->integer('blood_per_turn');
            $table->integer('max_trait_rating');
            $table->text('notes');
        });

        DB::statement('ALTER TABLE canon_generations ADD CONSTRAINT canon_generations_generation_check CHECK (generation >= 3 AND generation <= 15)');
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_generations');
    }
};
