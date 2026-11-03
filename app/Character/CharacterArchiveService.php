<?php

namespace App\Character;

use App\Character\Exceptions\InvalidCharacterInputException;
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

    public function delete(Character $character): void
    {
        if ($character->is_active) {
            throw new InvalidCharacterInputException('Сначала архивируйте персонажа.');
        }

        if ($character->authoredMessages()->exists()) {
            throw new InvalidCharacterInputException('Нельзя удалить персонажа, за которым есть сообщения в чате.');
        }

        $character->delete();
    }
}
