<?php

namespace App\Http\Controllers;

use App\Chronicle\ChronicleLocationService;
use App\Models\ChronicleLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChronicleLocationImageController extends Controller
{
    public function __construct(private ChronicleLocationService $locations) {}

    public function store(Request $request, ChronicleLocation $chronicleLocation): JsonResponse
    {
        $this->authorize('update', $chronicleLocation);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $path = $this->locations->storeImage($chronicleLocation, $validated['image']);

        return response()->json([
            'image_path' => $path,
        ]);
    }

    public function show(ChronicleLocation $chronicleLocation): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $chronicleLocation);

        $path = $chronicleLocation->image_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Location image not found.'], 404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}
