<?php

namespace App\Http\Controllers;

use App\Character\CharacterAccess;
use App\Character\CharacterBiographyService;
use App\Character\CharacterIdentityService;
use App\Character\CharacterPlaceService;
use App\Character\CharacterSheetReader;
use App\Character\CharacterTraitService;
use App\Character\DisciplineService;
use App\Character\Exceptions\BiographyUnchangedException;
use App\Chronicle\CurrentChronicle;
use App\Enums\CharacterBiographyStatus;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterBiographyRequest;
use App\Http\Requests\UpdateCharacterDisciplinesRequest;
use App\Http\Requests\UpdateCharacterIdentityRequest;
use App\Http\Requests\UpdateCharacterPlaceRequest;
use App\Http\Requests\UpdateCharacterTraitsRequest;
use App\Models\CanonClan;
use App\Models\CanonDiscipline;
use App\Models\CanonSect;
use App\Models\Character;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;
use App\Scene\ActiveSceneResolver;
use App\Scene\SceneParticipantService;
use App\World\WorldEntityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharacterSheetController extends Controller
{
    public function __construct(
        private CharacterSheetReader $reader,
        private WorldEntityService $entities,
        private CharacterIdentityService $identity,
        private DisciplineService $disciplines,
        private CharacterBiographyService $biographies,
        private CharacterPlaceService $place,
        private CharacterTraitService $traits,
        private SceneParticipantService $participants,
        private CurrentChronicle $currentChronicle,
        private ActiveSceneResolver $activeSceneResolver,
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
            'disciplines' => $disciplines,
            'clans' => $clans,
            'bloodlines' => $bloodlines,
            'sects' => $sects,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'chronicle_id' => ['prohibited'],
        ]);
        $chronicleId = $this->currentChronicle->id();
        $user = $request->user();

        $query = Character::query()->where('chronicle_id', $chronicleId)->where('is_active', true);

        if (! $user?->isStoryteller()) {
            $pcId = Character::query()
                ->where('user_id', $user?->id)
                ->where('character_type', CharacterType::Player)
                ->value('id');

            if ($pcId === null) {
                return response()->json(['characters' => [], 'archived' => []]);
            }

            $query->whereIn('id', $this->visibleCharacterIdsForPlayer($chronicleId, (int) $pcId));
        }

        $characters = $query->with('entity')->orderBy('id')->get();

        $payload = $characters->map(function (Character $character): array {
            return $this->listItem($character);
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

        return DB::transaction(function () use ($validated) {
            $chronicle = $this->currentChronicle->get();

            $typed = [
                'character_type' => CharacterType::from($validated['character_type']),
            ];

            if (isset($validated['user_id'])) {
                $typed['user_id'] = (int) $validated['user_id'];
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

            $entity = $this->entities->create(
                $chronicle,
                WorldEntityType::Character,
                $validated['canonical_name'],
                typed: $typed,
            );

            $character = Character::query()->findOrFail($entity->id);

            return response()->json(['character' => $this->reader->aggregate($character)], 201);
        });
    }

    public function show(Request $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        return response()->json(['character' => $this->reader->aggregate($character)]);
    }

    public function update(UpdateCharacterIdentityRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        $this->identity->update($character, $request->validated());

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updateDisciplines(UpdateCharacterDisciplinesRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

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
        } catch (BiographyUnchangedException) {
        }

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updateTraits(UpdateCharacterTraitsRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        $this->traits->sync($character, $request->validated('traits') ?? []);

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updatePlace(UpdateCharacterPlaceRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        return DB::transaction(function () use ($request, $character) {
            $this->place->apply($character, $request->validated());

            return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
        });
    }

    public function archive(Character $character): JsonResponse
    {
        $this->hideCharacter($character);

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function restore(Character $character): JsonResponse
    {
        $this->restoreCharacter($character);

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    /**
     * @return list<int>
     */
    private function visibleCharacterIdsForPlayer(int $chronicleId, int $pcId): array
    {
        $visibleIds = [$pcId];

        $activeScene = $this->activeSceneResolver->activeOrNull();

        if ($activeScene === null) {
            return $visibleIds;
        }

        $participantIds = SceneParticipant::query()
            ->where('scene_id', $activeScene->id)
            ->where('is_current', true)
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$visibleIds, ...$participantIds]));
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Character $character): array
    {
        return [
            'id' => (int) $character->id,
            'canonical_name' => $character->entity === null
                ? ''
                : (string) $character->entity->canonical_name,
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
            ->with('entity')
            ->orderBy('id')
            ->get();

        return $characters->map(fn (Character $character): array => [
            'id' => (int) $character->id,
            'canonical_name' => $character->entity === null
                ? ''
                : (string) $character->entity->canonical_name,
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
