<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chronicles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('setting', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreignId('chronicle_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE game_sessions ALTER COLUMN chronicle_id SET NOT NULL');

        DB::statement('DROP INDEX IF EXISTS game_sessions_single_active');
        DB::statement(
            "CREATE UNIQUE INDEX game_sessions_one_active_per_chronicle
             ON game_sessions (chronicle_id)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_sessions_one_active_per_chronicle');

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chronicle_id');
        });

        Schema::dropIfExists('chronicles');

        DB::statement(
            "CREATE UNIQUE INDEX game_sessions_single_active
             ON game_sessions (status)
             WHERE status = 'active'"
        );
    }
};
