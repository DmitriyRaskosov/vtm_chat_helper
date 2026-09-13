<?php

namespace Database\Factories;

use App\Enums\RuleDocumentStatus;
use App\Models\Chronicle;
use App\Models\ChronicleRuleOverride;
use App\Models\RuleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChronicleRuleOverride>
 */
class ChronicleRuleOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'rule_document_id' => RuleDocument::factory(),
            'override_text' => 'В этой хронике Dominate не действует на гулей.',
            'status' => RuleDocumentStatus::Approved,
            'current_version' => 1,
            'change_reason' => 'House rule.',
            'created_by' => null,
            'approved_at' => now(),
            'approved_by' => null,
        ];
    }
}
