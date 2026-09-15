<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('driver', 32);
            $table->string('model', 120);
            $table->jsonb('raw_response');
            $table->jsonb('candidates');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['chronicle_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};
