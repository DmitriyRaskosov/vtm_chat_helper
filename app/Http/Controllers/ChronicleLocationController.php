<?php

namespace App\Http\Controllers;

use App\Chronicle\ChronicleLocationService;
use App\Http\Requests\StoreChronicleLocationRequest;
use App\Http\Requests\UpdateChronicleLocationRequest;
use App\Http\Resources\ChronicleLocationResource;
use App\Models\Chronicle;
use App\Models\ChronicleLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChronicleLocationController extends Controller
{
    public function __construct(private ChronicleLocationService $locations) {}

    public function index(Request $request, Chronicle $chronicle): JsonResponse
    {
        $this->authorize('update', $chronicle);

        $items = $this->locations->listForChronicle($chronicle)
            ->map(fn (ChronicleLocation $location): array => (new ChronicleLocationResource($location))->toArray($request));

        return response()->json([
            'locations' => $items->values()->all(),
        ]);
    }

    public function store(StoreChronicleLocationRequest $request, Chronicle $chronicle): JsonResponse
    {
        $this->authorize('update', $chronicle);

        $location = $this->locations->create($chronicle, $request->validated());

        return response()->json([
            'location' => (new ChronicleLocationResource($location))->toArray($request),
        ], 201);
    }

    public function update(
        UpdateChronicleLocationRequest $request,
        ChronicleLocation $chronicleLocation,
    ): JsonResponse {
        $this->authorize('update', $chronicleLocation);

        $location = $this->locations->update($chronicleLocation, $request->validated());

        return response()->json([
            'location' => (new ChronicleLocationResource($location))->toArray($request),
        ]);
    }

    public function destroy(ChronicleLocation $chronicleLocation): JsonResponse
    {
        $this->authorize('delete', $chronicleLocation);

        $this->locations->delete($chronicleLocation);

        return response()->json(['deleted' => true]);
    }
}
