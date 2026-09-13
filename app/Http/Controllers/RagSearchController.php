<?php

namespace App\Http\Controllers;

use App\Http\Requests\RagSearchRequest;
use App\Rag\MessageSearcher;
use Illuminate\Http\JsonResponse;

class RagSearchController extends Controller
{
    public function __invoke(RagSearchRequest $request, MessageSearcher $searcher): JsonResponse
    {
        $results = $searcher->search(
            $request->integer('chronicle_id'),
            (string) $request->validated('q'),
            $request->integer('limit', 5),
            $request->validated('game_session_id'),
            $request->validated('scene_id'),
        );

        return response()->json([
            'results' => $results->map(fn ($result) => [
                'id' => $result->id,
                'source_type' => 'message',
                'source_id' => (string) $result->message_id,
                'content' => $result->content,
                'distance' => $result->neighbor_distance,
            ]),
        ]);
    }
}
