<?php

namespace App\Http\Controllers;

use App\Dice\DiceMacroService;
use App\Http\Requests\StoreDiceMacroRequest;
use App\Http\Requests\UpdateDiceMacroRequest;
use App\Http\Resources\DiceMacroResource;
use App\Models\DiceMacro;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiceMacroController extends Controller
{
    public function __construct(
        private DiceMacroService $macros,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $macros = $this->macros->list($this->user($request))
            ->map(fn (DiceMacro $macro): array => (new DiceMacroResource($macro))->toArray($request));

        return response()->json([
            'macros' => $macros->values()->all(),
        ]);
    }

    public function store(StoreDiceMacroRequest $request): JsonResponse
    {
        $macro = $this->macros->create($this->user($request), $request->validated());

        return response()->json([
            'macro' => (new DiceMacroResource($macro))->toArray($request),
        ], 201);
    }

    public function update(UpdateDiceMacroRequest $request, DiceMacro $macro): JsonResponse
    {
        $macro = $this->macros->update($macro, $request->validated());

        return response()->json([
            'macro' => (new DiceMacroResource($macro))->toArray($request),
        ]);
    }

    public function destroy(DiceMacro $macro): JsonResponse
    {
        $this->macros->delete($macro);

        return response()->json(['deleted' => true]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated.');
        }

        return $user;
    }
}
