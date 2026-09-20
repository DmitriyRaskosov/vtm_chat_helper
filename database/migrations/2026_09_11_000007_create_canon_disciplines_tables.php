<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_disciplines', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('description');
            $table->boolean('is_common');
            $table->timestamps();
        });

        Schema::create('canon_clan_disciplines', function (Blueprint $table) {
            $table->foreignId('clan_id')
                ->constrained('canon_clans')
                ->restrictOnDelete();
            $table->foreignId('discipline_id')
                ->constrained('canon_disciplines')
                ->restrictOnDelete();
            $table->boolean('is_in_clan');

            $table->primary(['clan_id', 'discipline_id']);
            $table->timestamps();
        });

        Schema::create('canon_discipline_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')
                ->constrained('canon_disciplines')
                ->restrictOnDelete();
            $table->integer('level');
            $table->text('name');
            $table->text('description');
            $table->text('system');
            $table->text('source_book');

            $table->unique(['discipline_id', 'level', 'name'], 'canon_discipline_powers_discipline_level_name_unique');
            $table->unique(['id', 'discipline_id'], 'canon_discipline_powers_id_discipline_unique');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE canon_discipline_powers ADD CONSTRAINT canon_discipline_powers_level_check CHECK (level >= 1 AND level <= 9)');
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_discipline_powers');
        Schema::dropIfExists('canon_clan_disciplines');
        Schema::dropIfExists('canon_disciplines');
    }
};
