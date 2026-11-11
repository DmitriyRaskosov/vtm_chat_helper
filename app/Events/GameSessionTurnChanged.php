<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameSessionTurnChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<int>  $sceneIds
     */
    public function __construct(
        public int $gameSessionId,
        public ?int $activeCharacterId,
        public array $sceneIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $sceneId): PrivateChannel => new PrivateChannel('scene.'.$sceneId),
            $this->sceneIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'GameSessionTurnChanged';
    }

    /**
     * @return array<string, int|null>
     */
    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->gameSessionId,
            'active_character_id' => $this->activeCharacterId,
        ];
    }
}
