<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_paths', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('description');
            $table->boolean('is_humanity');
            $table->text('virtue_conscience');
            $table->text('virtue_selfcontrol');
        });

        DB::statement("ALTER TABLE canon_paths ADD CONSTRAINT canon_paths_virtue_conscience_check CHECK (virtue_conscience IN ('Conscience', 'Conviction'))");
        DB::statement("ALTER TABLE canon_paths ADD CONSTRAINT canon_paths_virtue_selfcontrol_check CHECK (virtue_selfcontrol IN ('Self-Control', 'Instinct'))");

        Schema::create('canon_path_sin_levels', function (Blueprint $table) {
            $table->integer('level')->primary();
            $table->text('label');
            $table->text('description');
        });

        DB::statement('ALTER TABLE canon_path_sin_levels ADD CONSTRAINT canon_path_sin_levels_level_check CHECK (level >= 1 AND level <= 10)');

        Schema::create('canon_path_sins', function (Blueprint $table) {
            $table->foreignId('path_id')
                ->constrained('canon_paths')
                ->restrictOnDelete();
            $table->integer('level');
            $table->text('sin');

            $table->primary(['path_id', 'level']);
        });

        Schema::table('canon_path_sins', function (Blueprint $table) {
            $table->foreign('level')
                ->references('level')
                ->on('canon_path_sin_levels')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_path_sins');
        Schema::dropIfExists('canon_path_sin_levels');
        Schema::dropIfExists('canon_paths');
    }
};
