<?php

namespace Tests\Unit\Extractor;

use App\Extractor\ExtractionPromptBuilder;
use App\Extractor\ExtractorTokenBudget;
use App\Models\WorldRelationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractorTokenBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_thousand_characters_equal_five_thousand_tokens_at_cpt_two(): void
    {
        config(['extractor.characters_per_token' => 2]);

        $budget = $this->app->make(ExtractorTokenBudget::class);

        $this->assertSame(5000, $budget->estimateTokens(str_repeat('А', 10000)));
    }

    public function test_full_lore_input_leaves_seven_thousand_one_hundred_twenty_eight_output_tokens(): void
    {
        config([
            'extractor.characters_per_token' => 2,
            'ollama.context_length' => 16384,
            'extractor.profiles.lore.output_tokens' => 7128,
        ]);

        $relationKeys = WorldRelationType::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $sliceText = str_repeat('А', 10000);
        $messages = $this->app->make(ExtractionPromptBuilder::class)->build(
            $sliceText,
            ['1 | faction | Камарилья | '],
            $relationKeys,
        );

        $budget = $this->app->make(ExtractorTokenBudget::class);

        $this->assertSame(7128, $budget->effectiveNumPredict($messages, 'lore'));
    }

    public function test_lore_system_prompt_is_at_most_three_thousand_tokens(): void
    {
        config(['extractor.characters_per_token' => 2]);

        $relationKeys = WorldRelationType::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $messages = $this->app->make(ExtractionPromptBuilder::class)->build(
            'Короткий текст.',
            [],
            $relationKeys,
        );

        $budget = $this->app->make(ExtractorTokenBudget::class);
        $systemTokens = $budget->estimateTokens($messages[0]['content']);

        $this->assertLessThanOrEqual(3000, $systemTokens);
    }
}
