<?php

namespace App\Console\Commands\Demo;

use App\Models\CanonDiscipline;
use Database\Seeders\CanonClanDisciplineSeeder;
use Database\Seeders\CanonClanRelationSeeder;
use Database\Seeders\CanonClanSectSeeder;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonDisciplineSeeder;
use Database\Seeders\CanonSectSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset game data (chronicles, characters, scenes, diary) but keep canon and users.';

    public function handle(): int
    {
        DB::transaction(function (): void {
            $this->warn('Truncating game data...');

            DB::statement('TRUNCATE character_diary_entries RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE copilot_requests RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE messages RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE scene_participants RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE scene_contexts RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE scenes RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE game_sessions RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE character_traits RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE character_biographies RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE character_disciplines RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE character_powers RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE characters RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE world_relations RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE world_entities RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE chronicle_invitations RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE chronicle_members RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE chronicles RESTART IDENTITY CASCADE');

            $this->info('Game data cleared.');

            $this->ensureCanonReferenceData();

            $this->call('db:seed', ['--class' => DemoSeeder::class]);
        });

        return self::SUCCESS;
    }

    private function ensureCanonReferenceData(): void
    {
        if (CanonDiscipline::query()->exists()) {
            return;
        }

        $this->info('Canon reference data missing; seeding canon tables...');

        $this->call(CanonSectSeeder::class);
        $this->call(CanonClanSeeder::class);
        $this->call(CanonClanSectSeeder::class);
        $this->call(CanonClanRelationSeeder::class);
        $this->call(CanonDisciplineSeeder::class);
        $this->call(CanonClanDisciplineSeeder::class);
    }
}
