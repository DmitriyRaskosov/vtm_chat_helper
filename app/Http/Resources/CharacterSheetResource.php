<?php

namespace App\Http\Resources;

use App\Character\CharacterSheetReader;
use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Полный лист персонажа ({character: ...}).
 *
 * @mixin Character
 */
class CharacterSheetResource extends JsonResource
{
    public function __construct(
        Character $resource,
        private readonly CharacterSheetReader $reader,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->reader->aggregate($this->resource);
        $user = $request->user();

        if (! $this->viewerIsStoryteller($user)) {
            unset($data['inventory']);
        }

        return $data;
    }

    private function viewerIsStoryteller(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return ChronicleMember::query()
            ->where('chronicle_id', $this->resource->chronicle_id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('role', ChronicleRole::Storyteller)
            ->exists();
    }
}
