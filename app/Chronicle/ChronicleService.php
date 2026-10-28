<?php

namespace App\Chronicle;

use App\Chronicle\Exceptions\ChronicleNotFoundException;
use App\Enums\ChronicleRole;
use App\Enums\ChronicleStatus;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChronicleService
{
    public function create(
        User $user,
        string $title,
        ?string $description,
        ?string $setting,
    ): ChronicleMember {
        return DB::transaction(function () use ($user, $title, $description, $setting): ChronicleMember {
            $chronicle = Chronicle::query()->create([
                'title' => $title,
                'description' => $description,
                'setting' => $setting,
                'status' => ChronicleStatus::Active,
                'created_by' => $user->id,
            ]);

            $membership = ChronicleMember::query()->create([
                'chronicle_id' => $chronicle->id,
                'user_id' => $user->id,
                'role' => ChronicleRole::Storyteller,
                'joined_at' => now(),
                'left_at' => null,
            ]);

            $membership->setRelation('chronicle', $chronicle);

            return $membership;
        });
    }

    /**
     * @return Collection<int, ChronicleMember>
     */
    public function listFor(User $user): Collection
    {
        return ChronicleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->with('chronicle')
            ->orderBy('chronicle_id')
            ->get();
    }

    public function show(User $user, Chronicle $chronicle): ChronicleMember
    {
        $membership = ChronicleMember::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->with('chronicle')
            ->first();

        if ($membership === null) {
            throw new ChronicleNotFoundException;
        }

        return $membership;
    }
}
