<?php

namespace Database\Seeders;

use App\World\WorldRelationTypeCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorldRelationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (WorldRelationTypeCatalog::definitions() as $definition) {
            $rows[] = [
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
            ];
        }

        DB::table('world_relation_types')->upsert(
            $rows,
            ['key'],
            [
                'display_name',
                'allowed_source_types',
                'allowed_target_types',
                'symmetric',
                'transitive',
                'inverse_key',
                'default_weight',
                'enabled',
                'updated_at',
            ],
        );
    }
}