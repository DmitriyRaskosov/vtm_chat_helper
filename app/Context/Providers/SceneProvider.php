<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CanonClan;
use App\Models\CanonSect;
use App\Models\Character;
use App\Models\CharacterTrait;
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

        $names = [];
        $characterIds = $participants->pluck('character_id')->map(fn ($id): int => (int) $id)->all();
        if ($characterIds !== []) {
            $names = WorldEntity::query()->whereIn('id', $characterIds)->pluck('canonical_name', 'id');
        }

        // Кланы, секты и титулы участников — по одному запросу на тип данных.
        $clansById = [];
        $sectsById = [];
        $charactersById = [];
        $titlesByCharacterId = [];
        if ($characterIds !== []) {
            $characters = Character::query()
                ->whereIn('id', $characterIds)
                ->get(['id', 'clan_id', 'sect_id']);

            $clanIds = $characters->pluck('clan_id')->filter()->unique()->values()->all();
            $sectIds = $characters->pluck('sect_id')->filter()->unique()->values()->all();

            if ($clanIds !== []) {
                $clansById = CanonClan::query()->whereIn('id', $clanIds)->pluck('name', 'id')->all();
            }
            if ($sectIds !== []) {
                $sectsById = CanonSect::query()->whereIn('id', $sectIds)->pluck('name', 'id')->all();
            }

            $charactersById = $characters->keyBy('id');

            foreach (CharacterTrait::query()
                ->whereIn('character_id', $characterIds)
                ->where('key', 'title')
                ->get(['character_id', 'value']) as $trait) {
                $title = trim((string) $trait->value);
                if ($title !== '') {
                    $titlesByCharacterId[(int) $trait->character_id] = $title;
                }
            }
        }

        foreach ($participants as $participant) {
            $charId = (int) $participant->character_id;
            $name = (string) ($names[$charId] ?? $names[(string) $charId] ?? '#'.$charId);

            // Метаданные: роль, титул (черта title), секта, клан.
            $meta = [$participant->role->value];

            $title = $titlesByCharacterId[$charId] ?? null;
            if ($title !== null) {
                $meta[] = $title;
            }

            $character = $charactersById[$charId] ?? null;
            if ($character !== null) {
                $sectName = $character->sect_id !== null ? ($sectsById[$character->sect_id] ?? null) : null;
                $clanName = $character->clan_id !== null ? ($clansById[$character->clan_id] ?? null) : null;

                if (is_string($sectName) && $sectName !== '') {
                    $meta[] = $sectName;
                }
                if (is_string($clanName) && $clanName !== '') {
                    $meta[] = $clanName;
                }
            }

            if (! $participant->visible) {
                $meta[] = 'hidden';
            }

            $lines[] = 'Present: '.$name.' ('.implode(', ', $meta).')';
        }

        // Длинные поля — после Present: LineTrimmer обрезает хвост; одна строка Situation
        // иначе съедает весь бюджет и участники не попадают в контекст.
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
