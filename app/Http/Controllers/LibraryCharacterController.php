<?php

namespace App\Http\Controllers;

use App\Character\CharacterSheetReader;
use App\Character\LibraryCharacterService;
use App\Http\Requests\ImportLibraryCharacterRequest;
use App\Http\Requests\StoreLibraryCharacterRequest;
use App\Http\Resources\CharacterSheetResource;
use App\Http\Resources\LibraryCharacterResource;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\LibraryCharacter;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibraryCharacterController extends Controller
{
    public function __construct(
        private LibraryCharacterService $library,
        private CharacterSheetReader $reader,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LibraryCharacter::class);

        $user = $this->user($request);

        $templates = LibraryCharacter::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (LibraryCharacter $row): array => (new LibraryCharacterResource($row))->toArray($request));

        return response()->json([
            'library_characters' => $templates->values()->all(),
        ]);
    }

    public function store(StoreLibraryCharacterRequest $request): JsonResponse
    {
        $this->authorize('create', LibraryCharacter::class);

        $user = $this->user($request);
        $validated = $request->validated();

        if (array_key_exists('character_id', $validated)) {
            $character = Character::query()->findOrFail((int) $validated['character_id']);
            $this->authorize('manageProfile', $character);
            $libraryCharacter = $this->library->createFromCharacter($user, $character);
        } else {
            $libraryCharacter = $this->library->createTemplate($user, $validated);
        }

        return response()->json([
            'library_character' => (new LibraryCharacterResource($libraryCharacter))->toArray($request),
        ], 201);
    }

    public function destroy(LibraryCharacter $libraryCharacter): JsonResponse
    {
        $this->authorize('delete', $libraryCharacter);

        $libraryCharacter->delete();

        return response()->json(['deleted' => true]);
    }

    public function import(ImportLibraryCharacterRequest $request, Chronicle $chronicle): JsonResponse
    {
        $user = $this->user($request);
        $libraryCharacter = LibraryCharacter::query()->findOrFail((int) $request->validated('library_character_id'));

        $this->authorize('import', [$libraryCharacter, $chronicle]);

        $character = $this->library->instantiateInChronicle(
            $libraryCharacter,
            $chronicle,
            $user,
            (string) $request->validated('canonical_name'),
        );

        return response()->json([
            'character' => (new CharacterSheetResource($character, $this->reader))->toArray($request),
        ], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
