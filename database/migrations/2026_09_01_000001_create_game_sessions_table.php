<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::create('chronicle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['chronicle_id', 'user_id']);
        });

        DB::statement(<<<'SQL'
ALTER TABLE chronicle_members ADD CONSTRAINT chronicle_members_role_check CHECK (
    role IN ('storyteller', 'player')
)
SQL);

        Schema::create('chronicle_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_id')->constrained()->restrictOnDelete();
            $table->string('token_hash')->unique();
            $table->string('role', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE chronicle_invitations ADD CONSTRAINT chronicle_invitations_role_check CHECK (
    role IN ('storyteller', 'player')
)
SQL);

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('title', 120);
            $table->string('status', 20)->default('archived');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            "CREATE UNIQUE INDEX game_sessions_single_active
             ON game_sessions (status)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_sessions_single_active');

        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('chronicle_invitations');
        Schema::dropIfExists('chronicle_members');
        Schema::dropIfExists('chronicles');
    }
};
