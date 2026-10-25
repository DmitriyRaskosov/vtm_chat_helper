<?php

namespace App\Http\Controllers;

use App\Models\Scene;
use App\Models\SceneContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SceneContextImageController extends Controller
{
    public function store(Request $request, Scene $scene): JsonResponse
    {
        $this->authorize('update', $scene);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->getClientOriginalExtension());
        $directory = 'scenes/'.$scene->id;
        $filename = 'location.'.$extension;
        $relativePath = $directory.'/'.$filename;

        $disk = Storage::disk('public');
        $context = $this->contextForScene($scene);

        if ($context->location_image_path !== null && $disk->exists($context->location_image_path)) {
            $disk->delete($context->location_image_path);
        }

        $disk->putFileAs($directory, $file, $filename);

        $context->location_image_path = $relativePath;
        $context->save();

        return response()->json([
            'location_image_path' => $relativePath,
        ]);
    }

    public function show(Scene $scene): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $scene);

        $context = SceneContext::query()->where('scene_id', $scene->id)->first();
        $path = $context?->location_image_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Location image not found.'], 404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    private function contextForScene(Scene $scene): SceneContext
    {
        $scene->loadMissing('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;

        return SceneContext::query()->firstOrCreate(
            ['scene_id' => $scene->id],
            [
                'chronicle_id' => $chronicleId,
                'revision' => 0,
            ],
        );
    }
}
