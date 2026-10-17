<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Полная запись дневника (show / update / regenerate).
 */
class DiaryEntryResource extends DiaryEntryListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'entry' => $this->resource->entry,
        ];
    }
}
