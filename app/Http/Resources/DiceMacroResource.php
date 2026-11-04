<?php

namespace App\Http\Resources;

use App\Models\DiceMacro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiceMacro
 */
class DiceMacroResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'name' => $this->name,
            'pool' => (int) $this->pool,
            'difficulty' => (int) $this->difficulty,
            'dice_type' => (int) $this->dice_type,
            'use_willpower' => (bool) $this->use_willpower,
            'stats' => $this->statNames(),
        ];
    }

    /**
     * @return list<string>
     */
    private function statNames(): array
    {
        $macro = $this->resource;
        $stats = $macro instanceof DiceMacro ? $macro->getAttribute('stats') : null;
        if (! is_array($stats)) {
            return [];
        }

        $names = [];
        foreach ($stats as $name) {
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
