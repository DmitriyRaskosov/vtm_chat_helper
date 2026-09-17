<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_clan_sects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')
                ->constrained('canon_clans')
                ->cascadeOnDelete();
            $table->foreignId('sect_id')
                ->constrained('canon_sects')
                ->cascadeOnDelete();
            $table->smallInteger('since_year')->nullable();
            $table->smallInteger('until_year')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['clan_id', 'sect_id', 'since_year']);
            $table->index(['clan_id']);
            $table->index(['sect_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_clan_sects');
    }
};
