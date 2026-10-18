<?php

namespace Database\Seeders;

use App\Enums\ChronicleRole;
use App\Enums\ChronicleStatus;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChronicleSeeder extends Seeder
{
    public function run(): void
    {
        if (Chronicle::query()->exists()) {
            return;
        }

        $chronicle = Chronicle::query()->create([
            'title' => 'Моя первая хроника',
            'status' => ChronicleStatus::Active,
            'created_by' => null,
        ]);

        $owner = User::query()->where('login', 'admin')->first()
            ?? User::query()->orderBy('id')->first();

        if ($owner === null) {
            return;
        }

        ChronicleMember::query()->create([
            'chronicle_id' => $chronicle->id,
            'user_id' => $owner->id,
            'role' => ChronicleRole::Storyteller,
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }
}
