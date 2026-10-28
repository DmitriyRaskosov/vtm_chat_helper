<?php

namespace App\Http\Controllers;

use App\Character\CharacterStateService;
use App\Http\Requests\UpdateCharacterStateRequest;
use App\Models\Character;
use Illuminate\Http\JsonResponse;

class CharacterStateController extends Controller
{
    public function __construct(
        private CharacterStateService $stateService,
    ) {}

    public function update(UpdateCharacterStateRequest $request, Character $character): JsonResponse
    {
        $this->authorize('updateState', $character);

        $state = $this->stateService->apply($character, $request->validated());

        return response()->json([
            'state' => $this->stateService->toArray($state),
        ]);
    }
}
