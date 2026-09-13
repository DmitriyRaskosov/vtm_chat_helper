<?php

namespace App\Http\Controllers;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryStatus;
use App\Http\Requests\UpsertLoreEntryRequest;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\LoreEntry;
use App\Models\User;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LoreEntryController extends Controller
{
    public function __construct(
        private LoreEntryService $lore,
        private LoreIndexer $indexer,
        private CharacterLoreKnowledgeService $knowledge,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        $query = LoreEntry::query()
            ->where('chronicle_id', $chronicleId)
            ->orderBy('title')
            ->orderBy('id');

        $active = (clone $query)->where('status', '!=', LoreEntryStatus::Archived)->get();
        $archived = (clone $query)->where('status', LoreEntryStatus::Archived)->get();

        return response()->json([
            'lore' => $active->map(fn (LoreEntry $entry): array => $this->serializeSummary($entry))->values()->all(),
            'archived' => $archived->map(fn (LoreEntry $entry): array => $this->serializeSummary($entry))->values()->all(),
        ]);
    }

    public function show(Request $request, LoreEntry $loreEntry): JsonResponse
    {
        $this->assertChronicle($request, $loreEntry);

        return response()->json(['lore' => $this->serialize($loreEntry)]);
    }

    public function store(UpsertLoreEntryRequest $request): JsonResponse
    {
        $chronicle = Chronicle::query()->findOrFail(
            Chronicle::resolveId(
                $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
            ),
        );

        try {
            $entry = $this->write($chronicle, $request->validated(), $request->user(), null);
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['lore' => $this->serialize($entry)], 201);
    }

    public function update(UpsertLoreEntryRequest $request, LoreEntry $loreEntry): JsonResponse
    {
        $this->assertChronicle($request, $loreEntry);
        $chronicle = Chronicle::query()->findOrFail($loreEntry->chronicle_id);

        try {
            $entry = $this->write($chronicle, $request->validated(), $request->user(), $loreEntry);
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['lore' => $this->serialize($entry)]);
    }

    public function archive(Request $request, LoreEntry $loreEntry): JsonResponse
    {
        $this->assertChronicle($request, $loreEntry);

        try {
            $entry = $this->lore->archive($loreEntry);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $this->indexer->rebuildApproved($entry);

        return response()->json(['lore' => $this->serialize($entry->refresh())]);
    }

    public function restore(Request $request, LoreEntry $loreEntry): JsonResponse
    {
        $this->assertChronicle($request, $loreEntry);

        $entry = $this->lore->restore($loreEntry);
        $this->indexer->rebuildApproved($entry);

        return response()->json(['lore' => $this->serialize($entry->refresh())]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function write(Chronicle $chronicle, array $validated, ?User $author, ?LoreEntry $entry): LoreEntry
    {
        $this->assertSameChronicle($chronicle, $validated);

        $fields = [
            'title' => $validated['title'],
            'canonical_text' => $validated['canonical_text'],
            'status' => LoreEntryStatus::Approved,
        ];
        if (isset($validated['kind'])) {
            $fields['kind'] = $validated['kind'];
        }
        if (isset($validated['visibility'])) {
            $fields['visibility'] = $validated['visibility'];
        }
        if (isset($validated['classification'])) {
            $fields['classification'] = $validated['classification'];
        }
        if (array_key_exists('situational', $validated)) {
            $fields['situational'] = (bool) $validated['situational'];
        }

        try {
            $entry = $this->lore->publish(
                $chronicle,
                $fields,
                'Правка с экрана мира',
                $entry,
                $author,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Lore entry is unchanged.') {
                throw $e;
            }

            if ($entry === null) {
                throw $e;
            }
        }

        if (array_key_exists('entity_ids', $validated)) {
            $this->lore->syncEntities($entry, $validated['entity_ids']);
        }
        if (
            array_key_exists('granted_character_ids', $validated)
            || array_key_exists('denied_character_ids', $validated)
        ) {
            $this->knowledge->syncExceptions(
                $entry,
                $validated['granted_character_ids'] ?? [],
                $validated['denied_character_ids'] ?? [],
                $author,
            );
        }
        $this->indexer->rebuildApproved($entry->refresh());

        return $entry->refresh();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertSameChronicle(Chronicle $chronicle, array $validated): void
    {
        if (array_key_exists('entity_ids', $validated)) {
            $ids = array_values(array_unique(array_map('intval', $validated['entity_ids'])));
            if ($ids !== []) {
                $matched = WorldEntity::query()
                    ->where('chronicle_id', $chronicle->id)
                    ->whereIn('id', $ids)
                    ->count();
                if ($matched !== count($ids)) {
                    throw new MixedChronicleException;
                }
            }
        }

        if (array_key_exists('granted_character_ids', $validated)) {
            $ids = array_values(array_unique(array_map('intval', $validated['granted_character_ids'])));
            if ($ids !== []) {
                $matched = Character::query()
                    ->where('chronicle_id', $chronicle->id)
                    ->whereIn('id', $ids)
                    ->count();
                if ($matched !== count($ids)) {
                    throw new MixedChronicleException;
                }
            }
        }

        if (array_key_exists('denied_character_ids', $validated)) {
            $ids = array_values(array_unique(array_map('intval', $validated['denied_character_ids'])));
            if ($ids !== []) {
                $matched = Character::query()
                    ->where('chronicle_id', $chronicle->id)
                    ->whereIn('id', $ids)
                    ->count();
                if ($matched !== count($ids)) {
                    throw new MixedChronicleException;
                }
            }
        }
    }

    private function assertChronicle(Request $request, LoreEntry $entry): void
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $entry->chronicle_id !== $chronicleId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSummary(LoreEntry $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'title' => $entry->title,
            'kind' => $entry->kind->value,
            'visibility' => $entry->visibility->value,
            'classification' => ($entry->classification ?? LoreAccessLevel::L0)->value,
            'situational' => (bool) $entry->situational,
            'status' => $entry->status->value,
            'current_version' => (int) $entry->current_version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LoreEntry $entry): array
    {
        $entry->loadMissing(['entityLinks.entity']);
        $links = $entry->entityLinks->sortBy('entity_id')->values();

        return [
            ...$this->serializeSummary($entry),
            'canonical_text' => $entry->canonical_text,
            'entity_ids' => $links->map(fn ($link): int => (int) $link->entity_id)->all(),
            'entities' => $links->map(fn ($link): array => [
                'id' => (int) $link->entity_id,
                'canonical_name' => $link->entity?->canonical_name,
                'entity_type' => $link->entity?->entity_type?->value,
            ])->all(),
            'granted_character_ids' => $this->knowledge->grantedCharacterIds($entry),
            'denied_character_ids' => $this->knowledge->deniedCharacterIds($entry),
        ];
    }
}
