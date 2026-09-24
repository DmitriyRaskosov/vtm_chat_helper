<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\SceneContext;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;

class SceneProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'scene';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $scene = $assembly->scene;
        $context = SceneContext::query()->where('scene_id', $scene->id)->first();
        $participants = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('is_current', true)
            ->orderBy('id')
            ->get();

        $lines = [
            'Scene: '.$scene->title,
            'Status: '.$scene->status->value,
            'Session: '.$assembly->gameSession->title,
            'Chronicle: '.$assembly->chronicle->title,
        ];

        if (is_string($assembly->chronicle->setting) && $assembly->chronicle->setting !== '') {
            $lines[] = 'Setting: '.$assembly->chronicle->setting;
        }
        if (is_string($scene->description) && $scene->description !== '') {
            $lines[] = 'Description: '.$scene->description;
        }

        $locationId = $context?->location_entity_id;
        if ($locationId !== null) {
            $locationName = WorldEntity::query()->whereKey($locationId)->value('canonical_name');
            if (is_string($locationName) && $locationName !== '') {
                $lines[] = 'Location: '.$locationName;
            }
        }
        if (is_string($context?->atmosphere) && $context->atmosphere !== '') {
            $lines[] = 'Atmosphere: '.$context->atmosphere;
        }
        if (is_string($context?->situation) && $context->situation !== '') {
            $lines[] = 'Situation: '.$context->situation;
        }
        if (is_string($context?->storyteller_notes) && $context->storyteller_notes !== '') {
            $lines[] = 'Storyteller notes: '.$context->storyteller_notes;
        }

        $names = [];
        $characterIds = $participants->pluck('character_id')->map(fn ($id): int => (int) $id)->all();
        if ($characterIds !== []) {
            $names = WorldEntity::query()->whereIn('id', $characterIds)->pluck('canonical_name', 'id');
        }

        foreach ($participants as $participant) {
            $name = (string) ($names[$participant->character_id] ?? $names[(string) $participant->character_id] ?? '#'.$participant->character_id);
            $visibility = $participant->visible ? 'visible' : 'hidden';
            $lines[] = 'Present: '.$name.' ('.$participant->role->value.', '.$visibility.')';
        }

        [$content, $truncated] = $this->trimmer->prefix('## Scene', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'scene_id' => (int) $scene->id,
            'game_session_id' => (int) $assembly->gameSession->id,
            'chronicle_id' => (int) $assembly->chronicle->id,
            'context_revision' => $context?->revision,
            'frozen_revision' => $context?->frozen_revision,
            'location_entity_id' => $context?->location_entity_id,
            'participant_character_ids' => $characterIds,
        ], $truncated ? 'description' : null);
    }
}
