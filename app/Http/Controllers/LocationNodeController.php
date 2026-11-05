<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParseLocationNodesRequest;
use App\Http\Requests\UpdateLocationNodeRequest;
use App\Http\Resources\LocationNodeResource;
use App\Models\ChronicleLocation;
use App\Models\LocationNode;
use App\Scene\Exceptions\InvalidLocationNodeInputException;
use App\Scene\Exceptions\LocationNodeParseFailedException;
use App\Scene\LocationNodeParserService;
use App\Scene\LocationNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationNodeController extends Controller
{
    public function __construct(
        private LocationNodeService $nodes,
        private LocationNodeParserService $parser,
    ) {}

    public function index(Request $request, ChronicleLocation $chronicleLocation): JsonResponse
    {
        $this->authorize('update', $chronicleLocation);

        $items = $this->nodes->listForLocation($chronicleLocation)
            ->map(fn (LocationNode $node): array => (new LocationNodeResource($node))->toArray($request));

        return response()->json([
            'location_nodes' => $items->values()->all(),
        ]);
    }

    public function parse(ParseLocationNodesRequest $request, ChronicleLocation $chronicleLocation): JsonResponse
    {
        $this->authorize('update', $chronicleLocation);

        try {
            $this->parser->parse($chronicleLocation, (string) $request->validated('environment_details'));
        } catch (LocationNodeParseFailedException $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        $items = $this->nodes->listForLocation($chronicleLocation)
            ->map(fn (LocationNode $node): array => (new LocationNodeResource($node))->toArray($request));

        return response()->json([
            'location_nodes' => $items->values()->all(),
        ]);
    }

    public function update(
        UpdateLocationNodeRequest $request,
        ChronicleLocation $chronicleLocation,
        LocationNode $locationNode,
    ): JsonResponse {
        $this->authorize('update', $chronicleLocation);

        try {
            $this->nodes->assertNodeInLocation($locationNode, $chronicleLocation);
            $node = $this->nodes->updateName($locationNode, $request->validated());
        } catch (InvalidLocationNodeInputException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'location_node' => (new LocationNodeResource($node))->toArray($request),
        ]);
    }

    public function destroy(ChronicleLocation $chronicleLocation, LocationNode $locationNode): JsonResponse
    {
        $this->authorize('update', $chronicleLocation);

        try {
            $this->nodes->assertNodeInLocation($locationNode, $chronicleLocation);
            $this->nodes->delete($locationNode);
        } catch (InvalidLocationNodeInputException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['deleted' => true]);
    }
}
