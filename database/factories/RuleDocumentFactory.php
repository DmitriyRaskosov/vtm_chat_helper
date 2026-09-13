<?php

namespace Database\Factories;

use App\Enums\RuleDocumentStatus;
use App\Models\RuleDocument;
use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleDocument>
 */
class RuleDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ruleset_id' => Ruleset::factory(),
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'current_version' => 1,
            'status' => RuleDocumentStatus::Draft,
            'created_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
