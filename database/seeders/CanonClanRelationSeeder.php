<?php

namespace Database\Seeders;

use App\Models\CanonClan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CanonClanRelationSeeder extends Seeder
{
    public function run(): void
    {
        $c = CanonClan::pluck('id', 'slug');

        $rows = [
            // === ВРАЖДА (симметричная) ===
            $this->sym($c['ventrue'], $c['lasombra'], 'hostile', -5,
                'Смертельные враги: обе элиты претендуют на власть над сородичами.'),
            $this->sym($c['brujah'], $c['ventrue'], 'hostile', -4,
                'Классовая ненависть: идеалисты против аристократии.'),
            $this->sym($c['tremere'], $c['gangrel'], 'hostile', -3,
                'Гангрелы не прощают экспериментов Тремеров над природой.'),
            $this->sym($c['tremere'], $c['assamite'], 'hostile', -4,
                'Проклятие крови: Ассамиты прокляты Тремерами в ответ на уничтожение их старейшин.'),
            $this->sym($c['tzimisce'], $c['gangrel'], 'hostile', -3,
                'Соседи-враги в Карпатах и Трансильвании.'),
            $this->sym($c['ventrue'], $c['brujah'], 'hostile', -4,
                'Взаимная классовая неприязнь.'),

            // === ЛЁГКАЯ НЕПРИЯЗНЬ / ПРЕЗРЕНИЕ (симметричная) ===
            $this->sym($c['toreador'], $c['nosferatu'], 'contempt', -3,
                'Тореадоры считают Носферату чудовищами; Носферату платят им презрением.'),
            $this->sym($c['tremere'], $c['ventrue'], 'contempt', -2,
                'Вентру не доверяют выскочкам-Тремерам, но вынуждены с ними работать.'),
            $this->sym($c['malkavian'], $c['tremere'], 'contempt', -2,
                'Малкавианцы видят Тремеров насквозь.'),

            // === СОЮЗЫ (симметричные) ===
            $this->sym($c['lasombra'], $c['tzimisce'], 'allied', 4,
                'Столпы Шабаша, связанные взаимной выгодой.'),
            $this->sym($c['ventrue'], $c['toreador'], 'allied', 3,
                'Политический союз внутри Камарильи: сила и шарм.'),

            // === РОДСТВЕННЫЕ (симметричные) ===
            $this->sym($c['gangrel'], $c['ravnos'], 'respect', 2,
                'Оба клана кочевников, взаимное уважение к вольной жизни.'),

            // === АСИММЕТРИЧНЫЕ ===
            // Тремеры уважают Вентру как правителей (но Вентру платят презрением — уже выше).
            $this->asym($c['tremere'], $c['ventrue'], 'respect', 2,
                'Тремеры признают политическое превосходство Вентру.'),
            // Последователи Сета плетут интриги против Ассамитов (но не наоборот).
            $this->asym($c['followers_of_set'], $c['assamites'], 'contempt', -3,
                'Сеттиты считают Ассамитов грубыми убийцами без изящества.'),
        ];

        DB::table('canon_clan_relations')->upsert(
            $rows,
            ['from_clan_id', 'to_clan_id', 'relation_type', 'since_year'],
            ['intensity', 'until_year', 'is_symmetric', 'note', 'source', 'updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sym(int $a, int $b, string $type, int $intensity, string $note): array
    {
        return [
            'from_clan_id' => $a,
            'to_clan_id' => $b,
            'relation_type' => $type,
            'intensity' => $intensity,
            'since_year' => null,
            'until_year' => null,
            'is_symmetric' => true,
            'note' => $note,
            'source' => 'V20 Core',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function asym(int $a, int $b, string $type, int $intensity, string $note): array
    {
        $row = $this->sym($a, $b, $type, $intensity, $note);
        $row['is_symmetric'] = false;

        return $row;
    }
}
