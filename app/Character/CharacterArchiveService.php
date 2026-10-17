<?php

namespace App\Character;

use App\Models\Character;
use App\Models\WorldEntity;
use App\Scene\SceneParticipantService;
use App\World\WorldEntityService;

final class CharacterArchiveService
{
    public function __construct(
        private WorldEntityService $entities,
        private SceneParticipantService $participants,
    ) {}

    public function archive(Character $character): void
    {
        $entity = WorldEntity::query()->findOrFail($character->id);
        $this->entities->archive($entity);
        $this->participants->leaveMutableScenes($character->refresh());
    }

    public function restore(Character $character): void
    {
        $entity = WorldEntity::query()->findOrFail($character->id);
        $this->entities->restore($entity);
    }
}
