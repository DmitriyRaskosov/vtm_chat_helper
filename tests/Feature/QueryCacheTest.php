<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class QueryCacheTest extends TestCase
{
    use JoinsChronicle;

    public function test_is_storyteller_in_issues_single_membership_query_per_request(): void
    {
        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $user = User::factory()->create();
        $chronicle = Chronicle::factory()->create([
            'title' => 'Кэш',
            'created_by' => $user->id,
        ]);
        $this->joinChronicle($user, $chronicle, ChronicleRole::Storyteller);

        $membershipQueries = 0;
        DB::listen(function ($query) use (&$membershipQueries): void {
            if (str_contains(strtolower($query->sql), 'chronicle_members')) {
                $membershipQueries++;
            }
        });

        $user->isStorytellerIn($chronicle);
        $user->isStorytellerIn($chronicle);

        $this->assertSame(1, $membershipQueries);
    }
}
