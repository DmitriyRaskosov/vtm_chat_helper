<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_attributes', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('category');
        });

        DB::statement("ALTER TABLE canon_attributes ADD CONSTRAINT canon_attributes_category_check CHECK (category IN ('physical', 'social', 'mental'))");

        Schema::create('canon_abilities', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('category');
        });

        DB::statement("ALTER TABLE canon_abilities ADD CONSTRAINT canon_abilities_category_check CHECK (category IN ('talent', 'skill', 'knowledge'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_abilities');
        Schema::dropIfExists('canon_attributes');
    }
};
