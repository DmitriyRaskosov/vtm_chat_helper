<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_sects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->text('name');
            $table->text('description');
            $table->integer('founded_year')->nullable();
            $table->integer('ended_year')->nullable();
            $table->boolean('is_independent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_sects');
    }
};
