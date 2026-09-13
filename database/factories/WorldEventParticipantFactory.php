<?php

namespace Database\Factories;

use App\Enums\WorldEventParticipantRole;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldEventParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldEventParticipant>
 */
class WorldEventParticipantFactory extends Factory
{
    public function definition(): array
    {
        $event = WorldEvent::factory()->create();

        return [
            'event_id' => $event->id,
            'entity_id' => WorldEntity::factory()->create(['chronicle_id' => $event->chronicle_id])->id,
            'participant_role' => WorldEventParticipantRole::Actor,
            'note' => null,
        ];
    }
}
