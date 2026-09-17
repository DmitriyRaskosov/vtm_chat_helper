<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_clan_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_clan_id')
                ->constrained('canon_clans')
                ->restrictOnDelete();
            $table->foreignId('to_clan_id')
                ->constrained('canon_clans')
                ->restrictOnDelete();
            $table->text('relation_type');
            $table->integer('intensity')->nullable();
            $table->integer('since_year')->nullable();
            $table->integer('until_year')->nullable();
            $table->text('note');
            $table->text('source');

            $table->unique(
                ['from_clan_id', 'to_clan_id', 'relation_type', 'since_year'],
                'canon_clan_relations_from_to_type_since_unique'
            );
        });

        DB::statement("ALTER TABLE canon_clan_relations ADD CONSTRAINT canon_clan_relations_relation_type_check CHECK (relation_type IN ('allied', 'hostile', 'feud', 'contempt', 'neutral', 'blood_bound'))");
        DB::statement('ALTER TABLE canon_clan_relations ADD CONSTRAINT canon_clan_relations_different_clans_check CHECK (from_clan_id <> to_clan_id)');
        DB::statement('ALTER TABLE canon_clan_relations ADD CONSTRAINT canon_clan_relations_intensity_check CHECK (intensity IS NULL OR (intensity >= -5 AND intensity <= 5))');
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_clan_relations');
    }
};
