<?php

namespace App\Http\Controllers;

use App\Character\CharacterArchiveService;
use App\Character\CharacterBiographyService;
use App\Character\CharacterCatalog;
use App\Character\CharacterCreationService;
use App\Character\CharacterIdentityService;
use App\Character\CharacterPlaceService;
use App\Character\CharacterSheetReader;
use App\Character\CharacterTraitService;
use App\Character\CharacterVisibility;
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
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterSheetResource;
use App\Models\Character;
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
        private CharacterVisibility $visibility,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->catalog->load()->toArray());
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', $this->currentChronicle->get());

        $request->validate([
            'chronicle_id' => ['prohibited'],
        ]);
        $chronicleId = $this->currentChronicle->id();
        $user = $request->user();

        $query = Character::query()->where('chronicle_id', $chronicleId)->where('is_active', true);

        $isStorytellerInChronicle = $user !== null && $user->isStorytellerIn($this->currentChronicle->get());

        if (! $isStorytellerInChronicle) {
            $pcId = Character::query()
                ->where('user_id', $user?->id)
                ->where('character_type', CharacterType::Player)
                ->where('chronicle_id', $chronicleId)
                ->value('id');

            if ($pcId === null) {
                return response()->json(['characters' => [], 'archived' => []]);
            }

            $query->whereIn('id', $this->visibility->visibleIdsForPlayer($chronicleId, (int) $pcId));
        }

        $characters = $query->with('entity')->orderBy('id')->get();

        $archivedModels = $isStorytellerInChronicle
            ? Character::query()
                ->where('chronicle_id', $chronicleId)
                ->where('is_active', false)
                ->with('entity')
                ->orderBy('id')
                ->get()
            : collect();

        return response()->json([
            'characters' => CharacterResource::collection($characters)->toArray($request),
            'archived' => CharacterResource::collection($archivedModels)->toArray($request),
        ]);
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        $this->authorize('view', $this->currentChronicle->get());
        $this->authorize('create', Character::class);

        $character = $this->creation->create($request->validated());

        return response()->json([
            'character' => (new CharacterSheetResource($character, $this->reader))->toArray($request),
        ], 201);
    }

    public function show(Request $request, Character $character): JsonResponse
    {
        $this->authorize('view', $character);

        return response()->json([
            'character' => (new CharacterSheetResource($character, $this->reader))->toArray($request),
        ]);
    }

    public function update(UpdateCharacterIdentityRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $this->identity->update($character, $request->validated());

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function updateDisciplines(UpdateCharacterDisciplinesRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $this->disciplines->sync($character, $request->validated('disciplines'));

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function updateBiography(UpdateCharacterBiographyRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

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

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function updateTraits(UpdateCharacterTraitsRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $this->traits->sync($character, $request->validated('traits') ?? []);

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function updatePlace(UpdateCharacterPlaceRequest $request, Character $character): JsonResponse
    {
        $this->authorize('updatePlace', $character);
        $this->authorize('update', $character);

        $this->place->apply($character, $request->validated());

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function archive(Request $request, Character $character): JsonResponse
    {
        $this->authorize('archive', $character);

        $this->archiveService->archive($character);

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }

    public function restore(Request $request, Character $character): JsonResponse
    {
        $this->authorize('restore', $character);

        $this->archiveService->restore($character);

        return response()->json([
            'character' => (new CharacterSheetResource($character->refresh(), $this->reader))->toArray($request),
        ]);
    }
}
