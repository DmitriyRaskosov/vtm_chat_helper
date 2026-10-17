<?php

namespace App\Http\Resources;

use App\Character\CharacterSheetReader;
use App\Models\Character;
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
        return $this->reader->aggregate($this->resource);
    }
}
