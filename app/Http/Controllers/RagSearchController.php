<?php

namespace App\Http\Controllers;

use App\Enums\RagSourceType;
use App\Http\Requests\RagSearchRequest;
use App\Rag\RagSearcher;
use Illuminate\Http\JsonResponse;

class RagSearchController extends Controller
{
    public function __invoke(RagSearchRequest $request, RagSearcher $searcher): JsonResponse
    {
        $types = $request->collect('types')
            ->map(fn (string $type): RagSourceType => RagSourceType::from($type))
            ->all();

        $chunks = $searcher->search(
            $request->validated('q'),
            $request->integer('limit', 5),
            $types === [] ? null : $types,
        );

        return response()->json([
            'results' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'source_type' => $chunk->source_type->value,
                'source_id' => $chunk->source_id,
                'title' => $chunk->title,
                'content' => $chunk->content,
                'distance' => $chunk->neighbor_distance,
            ]),
        ]);
    }
}
