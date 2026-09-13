<?php

namespace App\Models;

use Database\Factories\WorldRelationTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'display_name',
    'allowed_source_types',
    'allowed_target_types',
    'symmetric',
    'transitive',
    'default_weight',
    'enabled',
])]
class WorldRelationType extends Model
{
    /** @use HasFactory<WorldRelationTypeFactory> */
    use HasFactory;

    /**
     * @return list<string>
     */
    public function allowedSourceTypeValues(): array
    {
        return array_values($this->allowed_source_types ?? []);
    }

    /**
     * @return list<string>
     */
    public function allowedTargetTypeValues(): array
    {
        return array_values($this->allowed_target_types ?? []);
    }

    protected function casts(): array
    {
        return [
            'allowed_source_types' => 'array',
            'allowed_target_types' => 'array',
            'symmetric' => 'boolean',
            'transitive' => 'boolean',
            'default_weight' => 'float',
            'enabled' => 'boolean',
        ];
    }
}
