<?php

namespace App\Character;

use App\Character\Exceptions\InvalidCharacterInputException;
use App\Enums\CharacterBiographyStatus;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterBiographyVersion;
use App\Models\CharacterDiscipline;
use App\Models\CharacterItem;
use App\Models\CharacterPower;
use App\Models\Chronicle;
use App\Models\LibraryCharacter;
use App\Models\LibraryCharacterBiography;
use App\Models\LibraryCharacterDiscipline;
use App\Models\LibraryCharacterItem;
use App\Models\LibraryCharacterPower;
use App\Models\User;
use App\World\Exceptions\InvalidWorldEntityInputException;
use App\World\WorldEntityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class LibraryCharacterService
{
    public function __construct(
        private WorldEntityService $entities,
    ) {}

    public function createFromCharacter(User $user, Character $character): LibraryCharacter
    {
        $character->loadMissing([
            'entity',
            'biography',
            'items',
            'disciplines',
            'powers',
        ]);

        return DB::transaction(function () use ($user, $character): LibraryCharacter {
            $entity = $character->entity;
            $name = $entity !== null && $entity->canonical_name !== ''
                ? $entity->canonical_name
                : 'Персонаж';

            $libraryCharacter = LibraryCharacter::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'character_type' => $character->character_type,
                'clan_id' => $character->clan_id,
                'sect_id' => $character->sect_id,
                'nature' => $character->nature,
                'demeanor' => $character->demeanor,
                'concept' => $character->concept,
                'generation' => $character->generation,
                'stats' => is_array($character->stats) ? $character->stats : null,
                'sheet_data' => is_array($character->sheet_data) ? $character->sheet_data : null,
            ]);

            $biography = $character->biography;
            if ($biography instanceof CharacterBiography) {
                LibraryCharacterBiography::query()->create([
                    'library_character_id' => $libraryCharacter->id,
                    'summary' => $biography->summary,
                    'full_text' => $biography->full_text,
                    'principles' => $biography->principles,
                    'motivation' => $biography->motivation,
                    'fears' => $biography->fears,
                    'desires' => $biography->desires,
                    'behavioral_rules' => $biography->behavioral_rules,
                ]);
            }

            foreach ($character->items as $item) {
                LibraryCharacterItem::query()->create([
                    'library_character_id' => $libraryCharacter->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => (int) $item->quantity,
                    'is_equipped' => (bool) $item->is_equipped,
                ]);
            }

            foreach ($character->disciplines as $row) {
                LibraryCharacterDiscipline::query()->create([
                    'library_character_id' => $libraryCharacter->id,
                    'discipline_id' => (int) $row->discipline_id,
                    'level' => (int) $row->level,
                ]);
            }

            foreach ($character->powers as $power) {
                LibraryCharacterPower::query()->create([
                    'library_character_id' => $libraryCharacter->id,
                    'discipline_id' => (int) $power->discipline_id,
                    'discipline_power_id' => (int) $power->discipline_power_id,
                    'acquired_at' => $power->acquired_at,
                    'note' => $power->note,
                    'parameters' => $power->parameters,
                ]);
            }

            return $libraryCharacter->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function createTemplate(User $user, array $fields): LibraryCharacter
    {
        $name = trim((string) ($fields['name'] ?? ''));
        if ($name === '') {
            throw new InvalidCharacterInputException('Укажите имя персонажа.');
        }

        if ($user->isStorytellerAnywhere()) {
            $requested = $fields['character_type'] ?? null;
            $type = $requested instanceof CharacterType
                ? $requested
                : CharacterType::from((string) $requested);
        } else {
            $type = CharacterType::Player;
        }

        $concept = $fields['concept'] ?? null;
        $clanId = $fields['clan_id'] ?? null;

        return LibraryCharacter::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'character_type' => $type,
            'clan_id' => $clanId === null || $clanId === '' ? null : (int) $clanId,
            'concept' => is_string($concept) && trim($concept) !== '' ? trim($concept) : null,
        ]);
    }

    public function instantiateInChronicle(
        LibraryCharacter $libraryCharacter,
        Chronicle $chronicle,
        User $user,
        string $canonicalName,
    ): Character {
        $libraryCharacter->loadMissing([
            'biography',
            'items',
            'disciplines.discipline',
            'powers',
        ]);

        $canonicalName = $this->entities->prepareCanonicalName($canonicalName);
        if ($canonicalName === '') {
            throw new InvalidCharacterInputException('Укажите имя персонажа.');
        }

        try {
            $this->entities->assertCanonicalNameAvailable($chronicle, $canonicalName);
        } catch (InvalidWorldEntityInputException) {
            throw new InvalidCharacterInputException('Это имя уже занято в хронике, выберите другое');
        }

        return DB::transaction(function () use ($libraryCharacter, $chronicle, $user, $canonicalName): Character {
            $typed = [
                'character_type' => $libraryCharacter->character_type,
                'clan_id' => $libraryCharacter->clan_id,
                'sect_id' => $libraryCharacter->sect_id,
                'nature' => $libraryCharacter->nature,
                'demeanor' => $libraryCharacter->demeanor,
                'concept' => $libraryCharacter->concept,
                'generation' => $libraryCharacter->generation,
            ];

            if ($libraryCharacter->character_type === CharacterType::Player) {
                $typed['user_id'] = $user->id;
            }

            $entity = $this->entities->create(
                $chronicle,
                WorldEntityType::Character,
                $canonicalName,
                typed: $typed,
            );

            $character = Character::query()->findOrFail($entity->id);
            $character->sect_id = $libraryCharacter->sect_id;

            $stats = is_array($libraryCharacter->stats) ? $libraryCharacter->stats : [];
            unset($stats['disciplines']);

            $character->stats = $stats !== [] ? CharacterStatsService::normalize($stats) : CharacterStatsService::defaults();
            $character->sheet_data = is_array($libraryCharacter->sheet_data)
                ? CharacterStatsService::normalizeSheetData($libraryCharacter->sheet_data)
                : CharacterStatsService::emptySheetData();
            $character->save();

            $libraryBio = $libraryCharacter->biography;
            if ($libraryBio instanceof LibraryCharacterBiography) {
                CharacterBiography::query()->create([
                    'character_id' => $character->id,
                    'summary' => $libraryBio->summary,
                    'full_text' => $libraryBio->full_text,
                    'principles' => $libraryBio->principles,
                    'motivation' => $libraryBio->motivation,
                    'fears' => $libraryBio->fears,
                    'desires' => $libraryBio->desires,
                    'behavioral_rules' => $libraryBio->behavioral_rules,
                    'current_version' => 1,
                    'status' => CharacterBiographyStatus::Approved,
                ]);

                CharacterBiographyVersion::query()->create([
                    'character_id' => $character->id,
                    'version' => 1,
                    'summary' => $libraryBio->summary,
                    'full_text' => $libraryBio->full_text,
                    'principles' => $libraryBio->principles,
                    'motivation' => $libraryBio->motivation,
                    'fears' => $libraryBio->fears,
                    'desires' => $libraryBio->desires,
                    'behavioral_rules' => $libraryBio->behavioral_rules,
                    'change_reason' => 'Импорт из библиотеки',
                    'created_by' => $user->id,
                ]);
            }

            foreach ($libraryCharacter->items as $item) {
                CharacterItem::query()->create([
                    'character_id' => $character->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => (int) $item->quantity,
                    'is_equipped' => (bool) $item->is_equipped,
                ]);
            }

            foreach ($libraryCharacter->disciplines as $row) {
                CharacterDiscipline::query()->create([
                    'character_id' => $character->id,
                    'discipline_id' => (int) $row->discipline_id,
                    'level' => (int) $row->level,
                ]);
            }

            foreach ($libraryCharacter->powers as $power) {
                CharacterPower::query()->create([
                    'character_id' => $character->id,
                    'discipline_id' => (int) $power->discipline_id,
                    'discipline_power_id' => (int) $power->discipline_power_id,
                    'acquired_at' => $power->acquired_at,
                    'note' => $power->note,
                    'parameters' => $power->parameters,
                ]);
            }

            $character->load('disciplines.discipline');

            return $this->syncDisciplineStats($character);
        });
    }

    /**
     * @param  Collection<int, CharacterDiscipline>  $rows
     * @return array<string, list<int>>
     */
    private function disciplinesJsonFromRows(Collection $rows): array
    {
        $disciplines = [];

        foreach ($rows as $row) {
            $slug = $row->discipline?->slug;
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $disciplines[$slug] = CharacterStatsService::dotTrack((int) $row->level, 5);
        }

        return $disciplines;
    }

    private function syncDisciplineStats(Character $character): Character
    {
        $stats = is_array($character->stats) ? $character->stats : [];
        $stats['disciplines'] = $this->disciplinesJsonFromRows($character->disciplines);
        $character->stats = CharacterStatsService::normalize($stats);
        $character->save();

        return $character->refresh();
    }
}
