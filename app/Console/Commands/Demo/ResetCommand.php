<?php

namespace App\Console\Commands\Demo;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCommand extends Command
{
    protected $signature = 'demo:reset';
    protected $description = 'Reset game data (chronicles, characters, scenes, diary) but keep canon and users.';

    public function handle(): int
    {
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
        DB::statement('TRUNCATE world_entity_aliases RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE world_entities RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE chronicles RESTART IDENTITY CASCADE');

        $this->info('Game data cleared.');

        $this->call('db:seed', ['--class' => DemoSeeder::class]);

        return self::SUCCESS;
    }
}