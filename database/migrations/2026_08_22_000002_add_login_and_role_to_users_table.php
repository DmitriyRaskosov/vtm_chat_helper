<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->nullable()->after('name');
            $table->string('role', 32)->default(UserRole::Player->value)->after('login');
        });

        DB::table('users')->whereNull('login')->update([
            'login' => DB::raw('email'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->nullable(false)->unique()->change();
            $table->dropUnique(['email']);
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn(['login', 'role']);
            $table->string('email')->nullable(false)->unique()->change();
        });
    }
};
