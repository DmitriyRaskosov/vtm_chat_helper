<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CombatTurnChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chronicleId,
        public int $sceneId,
        public int $combatEncounterId,
        public int $currentRound,
        public int $currentTurnIndex,
        public ?int $activeCharacterId,
        public string $status,
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
        return 'CombatTurnChanged';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'combat_encounter_id' => $this->combatEncounterId,
            'current_round' => $this->currentRound,
            'current_turn_index' => $this->currentTurnIndex,
            'active_character_id' => $this->activeCharacterId,
            'status' => $this->status,
        ];
    }
}
