<?php

namespace Database\Factories;

use App\Enums\RuleDocumentStatus;
use App\Models\RuleDocument;
use App\Models\RuleDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleDocumentVersion>
 */
class RuleDocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rule_document_id' => RuleDocument::factory(),
            'version' => 1,
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'status' => RuleDocumentStatus::Draft,
            'change_reason' => 'Первая версия.',
            'created_by' => null,
        ];
    }
}
