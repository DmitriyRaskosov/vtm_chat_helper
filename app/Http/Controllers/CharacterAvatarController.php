<?php

namespace App\Http\Controllers;

use App\Character\CharacterAvatarService;
use App\Http\Requests\StoreCharacterAvatarRequest;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class CharacterAvatarController extends Controller
{
    public function __construct(private CharacterAvatarService $avatars) {}

    public function store(StoreCharacterAvatarRequest $request, Character $character): JsonResponse
    {
        $this->authorize('manageProfile', $character);

        /** @var UploadedFile $file */
        $file = $request->file('avatar');

        $updated = $this->avatars->upload($character, $file);

        return response()->json([
            'avatar_path' => $updated->avatar_path,
        ]);
    }
}
