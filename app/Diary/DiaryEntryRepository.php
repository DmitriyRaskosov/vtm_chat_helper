<?php

namespace App\Diary;

use App\Diary\Exceptions\DiaryChronicleMismatchException;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class DiaryEntryRepository
{
    public function insertL0(
        Character $npc,
        Scene $scene,
        SceneParticipant $participant,
        int $fromMessageId,
        int $toMessageId,
        string $entry,
        ?float $importance,
        ?string $emotion,
        string $vectorLiteral,
        ?DateTimeInterface $inGameDate = null,
        bool $isTruncated = false,
    ): void {
        $this->assertNpcMatchesSceneChronicle($npc, $scene);

        DB::transaction(function () use (
            $npc,
            $scene,
            $participant,
            $fromMessageId,
            $toMessageId,
            $entry,
            $importance,
            $emotion,
            $vectorLiteral,
            $inGameDate,
            $isTruncated,
        ): void {
            DB::statement(
                'INSERT INTO character_diary_entries
                    (character_id, chronicle_id, scene_id, level, from_message_id, to_message_id, entry, importance, emotion, in_game_date, is_truncated, embedding, created_at, updated_at)
                VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?::vector, NOW(), NOW())',
                [
                    $npc->id,
                    $npc->chronicle_id,
                    $scene->id,
                    $fromMessageId,
                    $toMessageId,
                    $entry,
                    $importance,
                    $emotion,
                    $inGameDate?->format('Y-m-d H:i:s'),
                    $isTruncated,
                    $vectorLiteral,
                ],
            );

            $participant->update(['last_diary_message_id' => $toMessageId]);
        });
    }

    public function insertL1(
        Character $npc,
        Scene $scene,
        int $fromMessageId,
        int $toMessageId,
        string $entry,
        ?float $importance,
        ?string $emotion,
        string $vectorLiteral,
        ?DateTimeInterface $inGameDate = null,
        bool $isTruncated = false,
    ): void {
        $this->assertNpcMatchesSceneChronicle($npc, $scene);

        DB::transaction(function () use (
            $npc,
            $scene,
            $fromMessageId,
            $toMessageId,
            $entry,
            $importance,
            $emotion,
            $vectorLiteral,
            $inGameDate,
            $isTruncated,
        ): void {
            DB::statement(
                'INSERT INTO character_diary_entries
                    (character_id, chronicle_id, scene_id, level, from_message_id, to_message_id, entry, importance, emotion, in_game_date, is_truncated, embedding, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?::vector, NOW(), NOW())',
                [
                    $npc->id,
                    $npc->chronicle_id,
                    $scene->id,
                    $fromMessageId,
                    $toMessageId,
                    $entry,
                    $importance,
                    $emotion,
                    $inGameDate?->format('Y-m-d H:i:s'),
                    $isTruncated,
                    $vectorLiteral,
                ],
            );
        });
    }

    public function updateContent(
        int $entryId,
        string $entry,
        ?float $importance,
        ?string $emotion,
        string $vectorLiteral,
        bool $isTruncated = false,
    ): void {
        DB::statement(
            'UPDATE character_diary_entries
             SET entry = ?, importance = ?, emotion = ?, is_truncated = ?, embedding = ?::vector, updated_at = NOW()
             WHERE id = ?',
            [
                $entry,
                $importance,
                $emotion,
                $isTruncated,
                $vectorLiteral,
                $entryId,
            ],
        );
    }

    private function assertNpcMatchesSceneChronicle(Character $npc, Scene $scene): void
    {
        $scene->loadMissing('gameSession');

        if ((int) $npc->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new DiaryChronicleMismatchException;
        }
    }
}
