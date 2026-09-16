<?php

use App\World\WorldRelationTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_relation_types', function (Blueprint $table) {
            $table->string('inverse_key', 64)->nullable()->after('transitive');
        });

        $definition = collect(WorldRelationTypeCatalog::definitions())
            ->firstWhere('key', 'part_of');

        if ($definition === null) {
            return;
        }

        $now = now();
        $exists = DB::table('world_relation_types')->where('key', 'part_of')->exists();

        if (! $exists) {
            DB::table('world_relation_types')->insert([
                'key' => $definition['key'],
                'display_name' => $definition['display_name'],
                'allowed_source_types' => json_encode($definition['allowed_source_types']),
                'allowed_target_types' => json_encode($definition['allowed_target_types']),
                'symmetric' => $definition['symmetric'],
                'transitive' => $definition['transitive'],
                'inverse_key' => $definition['inverse_key'],
                'default_weight' => $definition['default_weight'],
                'enabled' => $definition['enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('world_relation_types')
                ->where('key', 'part_of')
                ->update([
                    'inverse_key' => $definition['inverse_key'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('world_relation_types')->where('key', 'part_of')->delete();

        Schema::table('world_relation_types', function (Blueprint $table) {
            $table->dropColumn('inverse_key');
        });
    }
};
