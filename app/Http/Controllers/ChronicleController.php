<?php

namespace App\Http\Controllers;

use App\Chronicle\ChronicleService;
use App\Http\Requests\StoreChronicleRequest;
use App\Http\Resources\ChronicleResource;
use App\Models\Chronicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChronicleController extends Controller
{
    public function __construct(
        private ChronicleService $chronicles,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $memberships = $this->chronicles->listFor($request->user());

        return response()->json([
            'chronicles' => $memberships
                ->map(fn ($membership): array => (new ChronicleResource($membership))->toArray($request))
                ->values(),
        ]);
    }

    public function store(StoreChronicleRequest $request): JsonResponse
    {
        $membership = $this->chronicles->create(
            $request->user(),
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('setting'),
        );

        return response()->json([
            'chronicle' => (new ChronicleResource($membership))->toArray($request),
        ], 201);
    }

    public function show(Request $request, Chronicle $chronicle): JsonResponse
    {
        $membership = $this->chronicles->show($request->user(), $chronicle);

        return response()->json([
            'chronicle' => (new ChronicleResource($membership))->toArray($request),
        ]);
    }
}
