<?php

namespace App\Console\Commands\Character;

use App\Character\CharacterStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CopyCharacterStateToStatsCommand extends Command
{
    protected $signature = 'characters:copy-state-to-stats';

    protected $description = 'Copy blood, willpower and health from character_states into characters.stats when stats is empty.';

    public function handle(): int
    {
        $copied = 0;

        DB::table('character_states')
            ->orderBy('character_id')
            ->chunkById(100, function ($rows) use (&$copied): void {
                foreach ($rows as $state) {
                    $copied += DB::table('characters')
                        ->where('id', $state->character_id)
                        ->whereNull('stats')
                        ->update([
                            'stats' => json_encode(CharacterStatsService::normalize([
                                'blood_pool' => (int) $state->blood_pool,
                                'willpower' => (int) $state->temporary_willpower,
                                'health' => [
                                    'level' => (int) $state->health_level,
                                    'damage' => (string) $state->health_damage,
                                    'penalty' => (int) $state->health_penalty,
                                ],
                            ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }, 'character_id');

        $this->info("Copied state into stats for {$copied} characters.");
        $rewritten = $this->rewriteLegacyKeys();
        $this->info("Rewrote legacy stat keys for {$rewritten} characters.");
        $converted = $this->convertDotTracks();
        $this->info("Converted dot tracks for {$converted} characters.");

        return self::SUCCESS;
    }

    private function rewriteLegacyKeys(): int
    {
        $rewritten = 0;

        DB::table('characters')
            ->whereNotNull('stats')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$rewritten): void {
                foreach ($rows as $row) {
                    $stats = json_decode((string) $row->stats, true);
                    if (! is_array($stats) || ! array_key_exists('temporary_willpower', $stats)) {
                        continue;
                    }

                    $will = is_array($stats['temporary_willpower'])
                        ? $stats['temporary_willpower']
                        : ['current' => (int) $stats['temporary_willpower'], 'is_temporary' => false];
                    $level = is_array($stats['health_level'] ?? null)
                        ? (int) ($stats['health_level']['current'] ?? 0)
                        : (int) ($stats['health_level'] ?? 0);

                    $stats['willpower'] = $will;
                    $stats['health'] = [
                        'level' => $level,
                        'damage' => (string) ($stats['health_damage'] ?? 'bashing'),
                        'penalty' => (int) ($stats['health_penalty'] ?? 0),
                    ];
                    unset($stats['temporary_willpower'], $stats['health_level'], $stats['health_damage'], $stats['health_penalty']);

                    $rewritten += DB::table('characters')
                        ->where('id', $row->id)
                        ->update([
                            'stats' => json_encode($stats, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });

        return $rewritten;
    }

    private function convertDotTracks(): int
    {
        $converted = 0;

        DB::table('characters')
            ->whereNotNull('stats')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$converted): void {
                foreach ($rows as $row) {
                    $stats = json_decode((string) $row->stats, true);
                    if (! is_array($stats) || ! $this->needsDotConversion($stats)) {
                        continue;
                    }

                    $converted += DB::table('characters')
                        ->where('id', $row->id)
                        ->update([
                            'stats' => json_encode(
                                CharacterStatsService::normalize($stats),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                            ),
                        ]);
                }
            });

        return $converted;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function needsDotConversion(array $stats): bool
    {
        if (array_key_exists('temporary_willpower', $stats) || array_key_exists('health_level', $stats)) {
            return true;
        }

        foreach ($stats as $key => $value) {
            if ($key === 'health') {
                continue;
            }

            if (! is_array($value) || ! array_is_list($value)) {
                return true;
            }
        }

        return false;
    }
}
