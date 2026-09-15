<?php

namespace App\Extractor;

use App\Models\Character;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;

class SceneSliceBuilder
{
    public function isEmpty(Scene $scene): bool
    {
        return Message::query()->where('scene_id', $scene->id)->doesntExist();
    }

    /**
     * @param  list<int>  $messageIds
     * @return array{
     *     participants: list<array{name: string, type: string}>,
     *     messages: list<array{id: int, author: string, body: string}>,
     *     message_ids: list<int>,
     *     truncated: bool,
     *     total_messages: int
     * }
     */
    public function buildFromMessageIds(Scene $scene, array $messageIds): array
    {
        if ($messageIds === []) {
            throw new \InvalidArgumentException('Scene slice requires at least one message id.');
        }

        $participants = $this->participants($scene);

        $messages = Message::query()
            ->where('scene_id', $scene->id)
            ->whereIn('id', $messageIds)
            ->orderBy('id')
            ->get();

        if ($messages->count() !== count($messageIds)) {
            throw new \InvalidArgumentException('Scene slice message ids must belong to the scene.');
        }

        $messageRows = [];
        $orderedIds = [];

        foreach ($messages as $message) {
            $orderedIds[] = (int) $message->id;
            $messageRows[] = [
                'id' => (int) $message->id,
                'author' => $message->displayAuthor(),
                'body' => (string) $message->body,
            ];
        }

        $total = Message::query()->where('scene_id', $scene->id)->count();

        return [
            'participants' => $participants,
            'messages' => $messageRows,
            'message_ids' => $orderedIds,
            'truncated' => false,
            'total_messages' => $total,
        ];
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function participants(Scene $scene): array
    {
        $participants = [];
        $participantRows = $scene->participants()
            ->where('is_current', true)
            ->orderBy('id')
            ->get();

        foreach ($participantRows as $participant) {
            $name = WorldEntity::query()->whereKey($participant->character_id)->value('canonical_name');
            $characterType = Character::query()
                ->whereKey($participant->character_id)
                ->value('character_type');

            $participants[] = [
                'name' => is_string($name) && $name !== '' ? $name : 'Unknown',
                'type' => is_object($characterType) ? $characterType->value : (string) $characterType,
            ];
        }

        return $participants;
    }

    /**
     * @param  array{
     *     participants: list<array{name: string, type: string}>,
     *     messages: list<array{id: int, author: string, body: string}>,
     *     truncated: bool,
     *     total_messages: int
     * }  $slice
     */
    public function formatForPrompt(array $slice): string
    {
        $lines = ['## Participants'];

        if ($slice['participants'] === []) {
            $lines[] = '(none)';
        } else {
            foreach ($slice['participants'] as $participant) {
                $lines[] = sprintf('%s | %s', $participant['name'], $participant['type']);
            }
        }

        $lines[] = '';
        $lines[] = '## Scene messages (id | author | body)';

        foreach ($slice['messages'] as $message) {
            $lines[] = sprintf(
                '%d | %s | %s',
                $message['id'],
                $message['author'],
                $message['body'],
            );
        }

        return implode("\n", $lines);
    }
}
