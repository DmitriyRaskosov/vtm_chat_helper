<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->unsignedBigInteger('last_extracted_to_message_id')->nullable()->after('ended_at');
            $table->foreign('last_extracted_to_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });

        Schema::table('extraction_runs', function (Blueprint $table) {
            $table->string('status', 20)->default('needs_review')->after('source_id');
            $table->string('trigger', 20)->nullable()->after('status');
            $table->unsignedBigInteger('from_message_id')->nullable()->after('trigger');
            $table->unsignedBigInteger('to_message_id')->nullable()->after('from_message_id');
            $table->foreignId('superseded_by_run_id')
                ->nullable()
                ->after('to_message_id')
                ->constrained('extraction_runs')
                ->nullOnDelete();

            $table->index(['chronicle_id', 'source_type', 'status']);
            $table->index(['source_type', 'source_id', 'from_message_id', 'to_message_id'], 'extraction_runs_scene_window_index');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table) {
            $table->dropIndex('extraction_runs_scene_window_index');
            $table->dropIndex(['chronicle_id', 'source_type', 'status']);
            $table->dropConstrainedForeignId('superseded_by_run_id');
            $table->dropColumn(['status', 'trigger', 'from_message_id', 'to_message_id']);
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_extracted_to_message_id');
        });
    }
};
