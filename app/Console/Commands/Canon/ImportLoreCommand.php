<?php

namespace App\Console\Commands\Canon;

use App\Models\CanonClan;
use App\Models\CanonLoreEntry;
use App\Models\CanonLoreEntryEntity;
use App\Models\CanonSect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class ImportLoreCommand extends Command
{
    protected $signature = 'canon:import-lore {--path= : Directory with .md files}';

    protected $description = 'Import canon lore from markdown files with YAML front-matter';

    /** @var array<string, array{class-string, string}> */
    private array $resolvers = [
        'sect' => [CanonSect::class, 'slug'],
        'clan' => [CanonClan::class, 'slug'],
    ];

    public function handle(): int
    {
        $path = $this->option('path') ?? resource_path('canon/lore');

        if (! File::isDirectory($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $files = File::files($path);
        $imported = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $parsed = YamlFrontMatter::parseFile($file->getPathname());
            $matter = $parsed->matter();

            if (empty($matter['slug']) || empty($matter['title'])) {
                $this->warn("Skip {$file->getFilename()}: missing slug or title");

                continue;
            }

            DB::transaction(function () use ($parsed, $matter) {
                $entry = CanonLoreEntry::updateOrCreate(
                    ['slug' => $matter['slug']],
                    [
                        'title' => $matter['title'],
                        'text' => $parsed->body(),
                        'category' => $matter['category'] ?? 'misc',
                        'tags' => $matter['tags'] ?? [],
                        'source_book' => $matter['source_book'] ?? null,
                        'source_page' => $matter['source_page'] ?? null,
                        'source_url' => $matter['source_url'] ?? null,
                        'retrieved_at' => $matter['retrieved_at'] ?? null,
                        'era_from' => $matter['era_from'] ?? null,
                        'era_to' => $matter['era_to'] ?? null,
                    ]
                );

                $entry->entities()->delete();

                foreach ($matter['entities'] ?? [] as $ref) {
                    $this->linkEntity($entry, $ref);
                }
            });

            $imported++;
            $this->info("Imported: {$matter['slug']}");
        }

        $this->info("Total: {$imported} entries.");

        return self::SUCCESS;
    }

    private function linkEntity(CanonLoreEntry $entry, array $ref): void
    {
        $type = $ref['type'] ?? null;
        $slug = $ref['slug'] ?? null;

        if (! $type || ! $slug) {
            $this->warn("Malformed entity ref in {$entry->slug}: ".json_encode($ref));

            return;
        }

        if (! isset($this->resolvers[$type])) {
            $this->warn("Unknown entity type '{$type}' in {$entry->slug}");

            return;
        }

        [$modelClass, $column] = $this->resolvers[$type];
        $id = $modelClass::where($column, $slug)->value('id');

        if (! $id) {
            $this->warn("Cannot resolve {$type}:{$slug} in {$entry->slug}");

            return;
        }

        CanonLoreEntryEntity::create([
            'lore_entry_id' => $entry->id,
            'entity_type' => $type,
            'entity_id' => $id,
            'note' => $ref['note'] ?? null,
        ]);
    }
}
