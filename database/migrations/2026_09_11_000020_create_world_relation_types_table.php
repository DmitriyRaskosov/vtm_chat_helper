<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_relation_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('display_name');
            $table->jsonb('allowed_source_types');
            $table->jsonb('allowed_target_types');
            $table->boolean('symmetric')->default(false);
            $table->boolean('transitive')->default(false);
            $table->string('inverse_key', 64)->nullable();
            $table->decimal('default_weight', 8, 4)->default(1);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_relation_types');
    }
};