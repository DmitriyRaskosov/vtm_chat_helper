<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ POST /characters/{character}/diary/preview (массив DiaryPreviewService, не Eloquent).
 */
class DiaryPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $preview */
        $preview = $this->resource;

        return $preview;
    }
}
