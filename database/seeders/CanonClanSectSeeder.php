<?php

namespace Database\Seeders;

use App\Models\CanonClan;
use App\Models\CanonSect;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CanonClanSectSeeder extends Seeder
{
    public function run(): void
    {
        $clans = CanonClan::pluck('id', 'slug');
        $sects = CanonSect::pluck('id', 'slug');

        $rows = [
            // === КАМАРИЛЬЯ ===
            $this->row($clans['brujah'], $sects['camarilla'], 1394),
            $this->row($clans['malkavian'], $sects['camarilla'], 1394),
            $this->row($clans['nosferatu'], $sects['camarilla'], 1394),
            $this->row($clans['toreador'], $sects['camarilla'], 1394),
            $this->row($clans['tremere'], $sects['camarilla'], 1394),
            $this->row($clans['ventrue'], $sects['camarilla'], 1394),

            // Гангрелы: в Камарилье с 1394 по 1999, потом ушли.
            $this->row($clans['gangrel'], $sects['camarilla'], 1394, 1999,
                'Покинули Камарилью после Недели Кошмаров.'),
            // И стали независимыми.
            $this->row($clans['gangrel'], $sects['independent'], 1999),

            // === ШАБАШ ===
            $this->row($clans['lasombra'], $sects['sabbat'], 1493),
            $this->row($clans['tzimisce'], $sects['sabbat'], 1493),

            // === НЕЗАВИСИМЫЕ ===
            $this->row($clans['assamite'], $sects['independent'], 1493),
            $this->row($clans['followers_of_set'], $sects['independent'], 1493),
            $this->row($clans['giovanni'], $sects['independent'], 1493),
            $this->row($clans['ravnos'], $sects['independent'], 1493),
        ];

        DB::table('canon_clan_sects')->upsert(
            $rows,
            ['clan_id', 'sect_id', 'since_year'],
            ['until_year', 'note', 'updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $clanId,
        int $sectId,
        int $sinceYear,
        ?int $untilYear = null,
        ?string $note = null,
    ): array {
        return [
            'clan_id' => $clanId,
            'sect_id' => $sectId,
            'since_year' => $sinceYear,
            'until_year' => $untilYear,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
