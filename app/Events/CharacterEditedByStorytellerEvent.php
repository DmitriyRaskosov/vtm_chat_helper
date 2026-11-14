<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CharacterEditedByStorytellerEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chronicleId,
        public int $characterId,
        public int $userId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chronicle.'.$this->chronicleId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CharacterEditedByStorytellerEvent';
    }

    /**
     * @return array{character_id: int, user_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'character_id' => $this->characterId,
            'user_id' => $this->userId,
        ];
    }
}
