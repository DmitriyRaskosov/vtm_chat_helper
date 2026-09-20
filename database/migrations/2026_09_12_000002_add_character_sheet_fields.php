<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('experience')->default(0)->after('is_active');
        });

        Schema::create('character_merits_flaws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('name', 160);
            $table->unsignedTinyInteger('cost');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['character_id', 'kind'], 'character_merits_flaws_character_kind_index');
        });

        DB::statement("ALTER TABLE character_merits_flaws ADD CONSTRAINT character_merits_flaws_kind_check CHECK (kind IN ('merit', 'flaw'))");

        Schema::create('character_health_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->unsignedTinyInteger('box_index');
            $table->string('damage', 16)->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'box_index'], 'character_health_boxes_character_index_unique');
        });

        DB::statement('ALTER TABLE character_health_boxes ADD CONSTRAINT character_health_boxes_index_check CHECK (box_index BETWEEN 0 AND 6)');
        DB::statement("ALTER TABLE character_health_boxes ADD CONSTRAINT character_health_boxes_damage_check CHECK (damage IS NULL OR damage IN ('bashing', 'lethal', 'aggravated'))");

        DB::statement('ALTER TABLE character_status DROP CONSTRAINT character_status_health_state_check');
        DB::statement("ALTER TABLE character_status ADD CONSTRAINT character_status_health_state_check CHECK (health_state IN ('healthy', 'bruised', 'hurt', 'injured', 'wounded', 'mauled', 'crippled', 'incapacitated', 'torpor'))");

    }

    public function down(): void
    {
        DB::statement('ALTER TABLE character_status DROP CONSTRAINT character_status_health_state_check');
        DB::statement("ALTER TABLE character_status ADD CONSTRAINT character_status_health_state_check CHECK (health_state IN ('healthy', 'bruised', 'injured', 'wounded', 'incapacitated', 'torpor'))");

        Schema::dropIfExists('character_health_boxes');
        Schema::dropIfExists('character_merits_flaws');

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('experience');
        });
    }
};
