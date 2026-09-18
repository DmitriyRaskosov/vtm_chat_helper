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
                ->cascadeOnDelete();
            $table->foreignId('to_clan_id')
                ->constrained('canon_clans')
                ->cascadeOnDelete();
            $table->string('relation_type', 32);
            $table->smallInteger('intensity')->nullable();
            $table->smallInteger('since_year')->nullable();
            $table->smallInteger('until_year')->nullable();
            $table->boolean('is_symmetric')->default(false);
            $table->text('note')->nullable();
            $table->string('source', 128)->nullable();
            $table->timestamps();

            $table->index(['from_clan_id']);
            $table->index(['to_clan_id']);
            $table->index(['relation_type']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX canon_clan_relations_from_to_type_since_unique '
            .'ON canon_clan_relations (from_clan_id, to_clan_id, relation_type, since_year) NULLS NOT DISTINCT',
        );
        DB::statement('ALTER TABLE canon_clan_relations ADD CONSTRAINT canon_clan_relations_different_clans_check CHECK (from_clan_id <> to_clan_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_clan_relations');
    }
};
