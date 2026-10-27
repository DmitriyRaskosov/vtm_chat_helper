<?php

namespace App\Http\Resources;

use App\Llm\CopilotDraftCreated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ POST /copilot/drafts (DTO сервиса, не Eloquent).
 *
 * @mixin CopilotDraftCreated
 */
class CopilotDraftsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $created = $this->resource;

        return [
            'copilot_request_id' => $created->copilotRequestId,
            'drafts' => $created->drafts,
        ];
    }
}
