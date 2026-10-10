<?php

namespace Database\Seeders;

use App\Enums\ChronicleStatus;
use App\Models\Chronicle;
use Illuminate\Database\Seeder;

class ChronicleSeeder extends Seeder
{
    public function run(): void
    {
        if (Chronicle::query()->exists()) {
            return;
        }

        Chronicle::query()->create([
            'title' => 'Моя первая хроника',
            'status' => ChronicleStatus::Active,
            'created_by' => null,
        ]);
    }
}