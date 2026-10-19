<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CharacterSheetImageController extends Controller
{
    public function store(Request $request, Character $character): JsonResponse
    {
        $this->authorize('updatePlace', $character);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->getClientOriginalExtension());
        $directory = 'characters/'.$character->id;
        $filename = 'sheet.'.$extension;
        $relativePath = $directory.'/'.$filename;

        $disk = Storage::disk('public');

        if ($character->sheet_image_path !== null && $disk->exists($character->sheet_image_path)) {
            $disk->delete($character->sheet_image_path);
        }

        $disk->putFileAs($directory, $file, $filename);

        $character->sheet_image_path = $relativePath;
        $character->save();

        return response()->json([
            'sheet_image_path' => $relativePath,
        ]);
    }

    public function show(Character $character): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $character);

        $path = $character->sheet_image_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Sheet image not found.'], 404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}
