<?php

namespace App\Http\Controllers;

use App\Character\CharacterAccess;
use App\Character\CharacterBiographyService;
use App\Character\CharacterIdentityService;
use App\Character\CharacterPlaceService;
use App\Character\CharacterSheetReader;
//use App\Character\DisciplineService;
//use App\Character\SheetCatalog;
use App\Enums\CharacterBiographyStatus;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterBiographyRequest;
use App\Http\Requests\UpdateCharacterDisciplinesRequest;
use App\Http\Requests\UpdateCharacterIdentityRequest;
use App\Http\Requests\UpdateCharacterPlaceRequest;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\CanonDiscipline;
use App\Models\WorldEntity;
use App\Models\CanonClan;
use App\Models\CanonSect;
use App\Scene\SceneParticipantService;
use App\World\WorldEntityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterSheetController extends Controller
{
    public function __construct(
        //private SheetCatalog $catalog,
        private CharacterSheetReader $reader,
        private WorldEntityService $entities,
        private CharacterIdentityService $identity,
        //private DisciplineService $disciplines,
        private CharacterBiographyService $biographies,
        private CharacterPlaceService $place,
        //private SceneParticipantService $participants,
    ) {}

    public function catalog(): JsonResponse
{
    $disciplines = CanonDiscipline::query()
    ->orderBy('name')
    ->get(['id', 'slug', 'name', 'description', 'is_common'])
    ->map(fn (CanonDiscipline $row): array => [
        'id' => (int) $row->id,
        'slug' => $row->slug,
        'name' => $row->name,
        'description' => $row->description,
        'is_common' => (bool) $row->is_common,
    ])
    ->values();

    $clans = CanonClan::query()
        ->where('is_playable', true)
        ->where('is_bloodline', false)
        ->orderBy('name')
        ->get(['id', 'slug', 'name', 'nickname'])
        ->map(fn (CanonClan $row): array => [
            'id' => (int) $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
            'nickname' => $row->nickname,
        ])
        ->values();

    $bloodlines = CanonClan::query()
        ->where('is_playable', true)
        ->where('is_bloodline', true)
        ->orderBy('name')
        ->get(['id', 'slug', 'name', 'nickname', 'parent_clan_id'])
        ->map(fn (CanonClan $row): array => [
            'id' => (int) $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
            'nickname' => $row->nickname,
            'parent_clan_id' => $row->parent_clan_id === null ? null : (int) $row->parent_clan_id,
        ])
        ->values();

    $sects = CanonSect::query()
        ->orderBy('name')
        ->get(['id', 'slug', 'name'])
        ->map(fn (CanonSect $row): array => [
            'id' => (int) $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
        ])
        ->values();

    return response()->json([
        //'catalog' => $this->catalog->definition(),
        //'traits' => $this->catalog->traits(),
        'disciplines' => $disciplines,
        'clans' => $clans,
        'bloodlines' => $bloodlines,
        'sects' => $sects,
    ]);
}

    public function index(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $user = $request->user();

        $query = Character::query()->where('chronicle_id', $chronicleId)->where('is_active', true);

        if (! $user?->isStoryteller()) {
            $pcId = Character::query()
                ->where('user_id', $user?->id)
                ->where('character_type', CharacterType::Player)
                ->value('id');

            if ($pcId === null) {
                return response()->json(['characters' => []]);
            }

            $query->where(function ($inner) use ($pcId): void {
                $inner->whereKey($pcId)->orWhere('domitor_character_id', $pcId);
            });
        }

        $characters = $query->orderBy('id')->get();
        $names = WorldEntity::query()
            ->whereIn('id', $characters->pluck('id'))
            ->pluck('canonical_name', 'id');

        $payload = $characters->map(function (Character $character) use ($names): array {
            return $this->listItem($character, $names);
        })->values();

        return response()->json([
            'characters' => $payload,
            'archived' => $user?->isStoryteller()
                ? $this->archivedList($chronicleId)
                : [],
        ]);
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicle = Chronicle::query()->findOrFail(
            Chronicle::resolveId(isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null),
        );

        $typed = [
            'character_type' => CharacterType::from($validated['character_type']),
        ];

        if (isset($validated['user_id'])) {
            $typed['user_id'] = (int) $validated['user_id'];
        }
        if (isset($validated['domitor_character_id'])) {
            $typed['domitor_character_id'] = (int) $validated['domitor_character_id'];
        }
        if (isset($validated['clan_id'])) {
            $typed['clan_id'] = (int) $validated['clan_id'];
        }
        if (isset($validated['sire_character_id'])) {
            $typed['sire_character_id'] = (int) $validated['sire_character_id'];
        }
        foreach (['generation', 'nature', 'demeanor', 'concept'] as $field) {
            if (array_key_exists($field, $validated)) {
                $typed[$field] = $validated[$field];
            }
        }

        try {
            $entity = $this->entities->create(
                $chronicle,
                WorldEntityType::Character,
                $validated['canonical_name'],
                typed: $typed,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        $character = Character::query()->findOrFail($entity->id);

        return response()->json(['character' => $this->reader->aggregate($character)], 201);
    }

    public function show(Request $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        return response()->json(['character' => $this->reader->aggregate($character)]);
    }

    public function update(UpdateCharacterIdentityRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        try {
            $this->identity->update($character, $request->validated());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updateDisciplines(UpdateCharacterDisciplinesRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        try {
            DB::transaction(function () use ($request, $character): void {
                foreach ($request->validated('disciplines') as $row) {
                    $discipline = CanonDiscipline::query()->findOrFail((int) $row['discipline_id']);
                    $level = (int) $row['level'];
                    if ($level === 0) {
                        $this->disciplines->clearCharacterDiscipline($character, $discipline);

                        continue;
                    }

                    $this->disciplines->setCharacterDiscipline($character, $discipline, $level);
                }
            });
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updateBiography(UpdateCharacterBiographyRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        $fields = [
            ...$request->validated(),
            'status' => CharacterBiographyStatus::Approved,
        ];

        try {
            $this->biographies->publish(
                $character,
                $fields,
                'Правка с листа',
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Biography is unchanged.') {
                abort(422, $e->getMessage());
            }
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updatePlace(UpdateCharacterPlaceRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        try {
            $this->place->apply($character, $request->validated());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function archive(Character $character): JsonResponse
    {
        $this->hideCharacter($character);

        if ($character->character_type !== CharacterType::Ghoul) {
            foreach ($character->ghouls as $ghoul) {
                if ($ghoul->is_active) {
                    $this->hideCharacter($ghoul);
                }
            }
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function restore(Character $character): JsonResponse
    {
        $this->restoreCharacter($character);

        if ($character->character_type !== CharacterType::Ghoul) {
            foreach ($character->ghouls()->where('is_active', false)->get() as $ghoul) {
                $this->restoreCharacter($ghoul);
            }
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeStats(Character $character, array $rows): void
    {
        foreach ($rows as $row) {
            $category = CharacterStatCategory::from((string) $row['category']);
            $key = (string) $row['stat_key'];
            $value = (int) $row['value'];
            $displayName = (string) ($row['display_name'] ?? $key);
            $maximum = isset($row['maximum']) ? (int) $row['maximum'] : 5;
            $sortOrder = (int) ($row['sort_order'] ?? 0);

            if ($value === 0 && $category !== CharacterStatCategory::Attribute) {
                $character->stats()
                    ->where('category', $category)
                    ->where('stat_key', $key)
                    ->delete();

                continue;
            }

            $stat = $this->stats->putStat(
                $character,
                $category,
                $key,
                $displayName,
                $value,
                $maximum,
                $sortOrder,
            );
        }
    }

    /**
     * @param  Collection<int, Character>  $ghouls
     * @param  Collection<int|string, string>  $names
     * @return array<string, mixed>
     */
    private function listItem(Character $character, $names, $ghouls): array
    {
        return [
            'id' => (int) $character->id,
            'canonical_name' => (string) ($names[$character->id] ?? $names[(string) $character->id] ?? ''),
            'character_type' => $character->character_type->value,
            'user_id' => $character->user_id === null ? null : (int) $character->user_id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function archivedList(int $chronicleId): array
    {
        $characters = Character::query()
            ->where('chronicle_id', $chronicleId)
            ->where('is_active', false)
            ->orderBy('id')
            ->get();
        $names = WorldEntity::query()
            ->whereIn('id', $characters->pluck('id'))
            ->pluck('canonical_name', 'id');

        return $characters->map(fn (Character $character): array => [
            'id' => (int) $character->id,
            'canonical_name' => (string) ($names[$character->id] ?? $names[(string) $character->id] ?? ''),
            'character_type' => $character->character_type->value,
            'user_id' => $character->user_id === null ? null : (int) $character->user_id,
        ])->values()->all();
    }

    private function hideCharacter(Character $character): void
    {
        $entity = WorldEntity::query()->findOrFail($character->id);
        $this->entities->archive($entity);
        $this->participants->leaveMutableScenes($character->refresh());
    }

    private function restoreCharacter(Character $character): void
    {
        $entity = WorldEntity::query()->findOrFail($character->id);
        $this->entities->restore($entity);
    }
}
