<?php

namespace App\Http\Controllers;

use App\Character\CharacterItemService;
use App\Http\Requests\StoreCharacterItemRequest;
use App\Http\Requests\UpdateCharacterItemRequest;
use App\Http\Resources\CharacterItemResource;
use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterItemController extends Controller
{
    public function __construct(
        private CharacterItemService $items,
    ) {}

    public function index(Request $request, Character $character): JsonResponse
    {
        $this->authorize('view', $character);

        $items = $this->items->list($character)
            ->map(fn (CharacterItem $item): array => (new CharacterItemResource($item))->toArray($request));

        return response()->json([
            'items' => $items->values()->all(),
        ]);
    }

    public function store(StoreCharacterItemRequest $request, Character $character): JsonResponse
    {
        $this->authorize('manageItems', $character);

        $item = $this->items->create($character, $request->validated());

        return response()->json([
            'item' => (new CharacterItemResource($item))->toArray($request),
        ], 201);
    }

    public function update(
        UpdateCharacterItemRequest $request,
        Character $character,
        CharacterItem $item,
    ): JsonResponse {
        $this->authorize('manageItems', $character);

        $item = $this->items->update($item, $request->validated());

        return response()->json([
            'item' => (new CharacterItemResource($item))->toArray($request),
        ]);
    }

    public function destroy(Character $character, CharacterItem $item): JsonResponse
    {
        $this->authorize('manageItems', $character);

        $this->items->delete($item);

        return response()->json(['deleted' => true]);
    }
}
