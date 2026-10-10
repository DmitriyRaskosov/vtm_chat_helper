<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldRelationTypeSeeder::class,
            CanonSectSeeder::class,
            CanonClanSeeder::class,
            CanonClanSectSeeder::class,
            CanonClanRelationSeeder::class,
            CanonDisciplineSeeder::class,
            CanonClanDisciplineSeeder::class,
            ChronicleSeeder::class,
        ]);

        Artisan::call('canon:import-lore');
    }
}