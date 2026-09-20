<?php

namespace Database\Seeders;

use App\Models\CanonClan;
use App\Models\CanonDiscipline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CanonClanDisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $clans = CanonClan::pluck('id', 'slug');
        $disc = CanonDiscipline::pluck('id', 'slug');

        $map = [
            'assamite'          => ['celerity', 'obfuscate', 'quietus'],
            'brujah'            => ['celerity', 'potence', 'presence'],
            'followers_of_set'  => ['obfuscate', 'presence', 'serpentis'],
            'gangrel'           => ['animalism', 'fortitude', 'protean'],
            'giovanni'          => ['dominate', 'necromancy', 'potence'],
            'lasombra'          => ['dominate', 'obtenebration', 'potence'],
            'malkavian'         => ['auspex', 'dementation', 'obfuscate'],
            'nosferatu'         => ['animalism', 'obfuscate', 'potence'],
            'ravnos'            => ['animalism', 'chimerstry', 'fortitude'],
            'toreador'          => ['auspex', 'celerity', 'presence'],
            'tremere'           => ['auspex', 'dominate', 'thaumaturgy'],
            'tzimisce'          => ['animalism', 'auspex', 'vicissitude'],
            'ventrue'           => ['dominate', 'fortitude', 'presence'],
        ];

        $rows = [];
        foreach ($map as $clanSlug => $disciplines) {
            if (!isset($clans[$clanSlug])) {
                continue;
            }
            foreach ($disciplines as $disciplineSlug) {
                if (!isset($disc[$disciplineSlug])) {
                    continue;
                }
                $rows[] = [
                    'clan_id' => $clans[$clanSlug],
                    'discipline_id' => $disc[$disciplineSlug],
                    'is_in_clan' => true,
                ];
            }
        }

        // Upsert нужен только для created/updated, но у нас нет timestamps в этой таблице (m2m без timestamps).
        // Поэтому — insertOrIgnore, чтобы повторный запуск не падал по PK.
        DB::table('canon_clan_disciplines')->insertOrIgnore($rows);
    }
}