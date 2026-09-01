<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedInteger('token_estimate')->nullable()->after('body');
            $table->string('token_estimator_version', 32)->nullable()->after('token_estimate');
        });

        $charactersPerToken = max(
            1,
            (int) config('context.token_estimator.characters_per_token', 3),
        );

        DB::table('messages')
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(500, function ($messages) use ($charactersPerToken): void {
                foreach ($messages as $message) {
                    DB::table('messages')
                        ->where('id', $message->id)
                        ->update([
                            'token_estimate' => max(
                                1,
                                (int) ceil(mb_strlen($message->body) / $charactersPerToken),
                            ),
                            'token_estimator_version' => 'unicode-chars-v1-cpt'.$charactersPerToken,
                        ]);
                }
            });

        DB::statement('ALTER TABLE messages ALTER COLUMN token_estimate SET NOT NULL');
        DB::statement('ALTER TABLE messages ALTER COLUMN token_estimator_version SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['token_estimate', 'token_estimator_version']);
        });
    }
};
