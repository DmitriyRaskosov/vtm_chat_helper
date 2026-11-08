<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePromptOverrideRequest;
use App\Http\Resources\WorkshopPromptResource;
use App\Prompt\PromptWorkshopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function __construct(
        private PromptWorkshopService $prompts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $prompts = array_map(
            fn (array $prompt): array => (new WorkshopPromptResource($prompt))->toArray($request),
            $this->prompts->list(),
        );

        return response()->json([
            'prompts' => $prompts,
        ]);
    }

    public function update(UpdatePromptOverrideRequest $request, string $key): JsonResponse
    {
        $prompt = $this->prompts->save($key, $request->validated('content'));

        return response()->json([
            'prompt' => (new WorkshopPromptResource($prompt))->toArray($request),
        ]);
    }

    public function destroy(string $key): JsonResponse
    {
        $this->prompts->reset($key);

        return response()->json(['deleted' => true]);
    }
}
