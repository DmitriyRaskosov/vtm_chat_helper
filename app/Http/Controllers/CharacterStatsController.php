<?php

namespace App\Http\Controllers;

use App\Character\CharacterStatsService;
use App\Http\Requests\UpdateCharacterStatsRequest;
use App\Http\Resources\CharacterStatsResource;
use App\Models\Character;
use Illuminate\Http\JsonResponse;

class CharacterStatsController extends Controller
{
    public function __construct(
        private CharacterStatsService $stats,
    ) {}

    public function update(UpdateCharacterStatsRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $saved = $this->stats->replace($character, $request->stats());

        return response()->json(
            (new CharacterStatsResource($saved))->toArray($request),
        );
    }
}
