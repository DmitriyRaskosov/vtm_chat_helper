<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
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
        Schema::dropIfExists('game_sessions');
    }
};
