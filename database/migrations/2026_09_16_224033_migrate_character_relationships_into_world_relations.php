<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Canonical world_relations shape lives in 2026_09_11_000011_create_world_relations_table.php.
        // Legacy character_relationships / character_affiliations tables are no longer created.
    }

    public function down(): void
    {
        // Data is intentionally retained. Structural rollback happens in later migrations.
    }
};
