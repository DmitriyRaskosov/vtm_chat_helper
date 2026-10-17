<?php

namespace App\Http\Controllers;

use App\Character\CharacterAccess;
use App\Character\CharacterArchiveService;
use App\Character\CharacterBiographyService;
use App\Character\CharacterCatalog;
use App\Character\CharacterCreationService;
use App\Character\CharacterIdentityService;
use App\Character\CharacterPlaceService;
use App\Character\CharacterSheetReader;
use App\Character\CharacterTraitService;
use App\Character\DisciplineService;
use App\Character\Exceptions\BiographyUnchangedException;
use App\Chronicle\CurrentChronicle;
use App\Enums\CharacterBiographyStatus;
use App\Enums\CharacterType;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterBiographyRequest;
use App\Http\Requests\UpdateCharacterDisciplinesRequest;
use App\Http\Requests\UpdateCharacterIdentityRequest;
use App\Http\Requests\UpdateCharacterPlaceRequest;
use App\Http\Requests\UpdateCharacterTraitsRequest;
use App\Models\Character;
use App\Models\SceneParticipant;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterSheetController extends Controller
{
    public function __construct(
        private CharacterSheetReader $reader,
        private CharacterCatalog $catalog,
        private CharacterCreationService $creation,
        private CharacterArchiveService $archiveService,
        private CharacterIdentityService $identity,
        private DisciplineService $disciplines,
        private CharacterBiographyService $biographies,
        private CharacterPlaceService $place,
        private CharacterTraitService $traits,
        private CurrentChronicle $currentChronicle,
        private ActiveSceneResolver $activeSceneResolver,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->catalog->load()->toArray());
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
        $character = $this->creation->create($request->validated());

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

        $this->identity->update($character, $request->validated());

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function updateDisciplines(UpdateCharacterDisciplinesRequest $request, Character $character): JsonResponse
    {
        CharacterAccess::abortUnlessManagesSheet($request->user(), $character);

        $this->disciplines->sync($character, $request->validated('disciplines'));

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

        $this->place->apply($character, $request->validated());

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function archive(Character $character): JsonResponse
    {
        $this->archiveService->archive($character);

        return response()->json(['character' => $this->reader->aggregate($character->refresh())]);
    }

    public function restore(Character $character): JsonResponse
    {
        $this->archiveService->restore($character);

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
}
