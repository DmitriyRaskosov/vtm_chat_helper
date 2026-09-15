<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('factions')->where('faction_type', 'guild')->update(['faction_type' => 'other']);
    }

    public function down(): void
    {
        // Guild was removed; former guild rows cannot be distinguished from other.
    }
};
