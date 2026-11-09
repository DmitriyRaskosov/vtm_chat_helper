import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { applyCombatTurn, filterBattleLogs, sameCombatScene } from './combatSync.js';

describe('combatSync', () => {
    it('matches a payload to the open scene', () => {
        assert.equal(sameCombatScene(4, { scene_id: 4 }), true);
        assert.equal(sameCombatScene(4, { scene_id: '4' }), true);
        assert.equal(sameCombatScene(4, { scene_id: 9 }), false);
        assert.equal(sameCombatScene(null, { scene_id: 4 }), false);
    });

    it('moves the active index and round from a turn event', () => {
        const next = applyCombatTurn(
            { id: 1, scene_id: 4, status: 'active', current_round: 1, current_turn_index: 0 },
            { scene_id: 4, current_round: 2, current_turn_index: 1, status: 'active' },
        );

        assert.equal(next.current_round, 2);
        assert.equal(next.current_turn_index, 1);
        assert.equal(next.status, 'active');
    });

    it('marks the encounter finished', () => {
        const next = applyCombatTurn(
            { id: 1, status: 'active', current_turn_index: 2 },
            { status: 'finished', active_character_id: null },
        );

        assert.equal(next.status, 'finished');
    });

    it('keeps only rolls of the active encounter', () => {
        const logs = [
            { id: 1, combat_encounter_id: null },
            { id: 2, combat_encounter_id: 7 },
            { id: 3, combat_encounter_id: 8 },
        ];

        assert.deepEqual(
            filterBattleLogs(logs, 7, true).map((row) => row.id),
            [2],
        );
        assert.deepEqual(
            filterBattleLogs(logs, 7, false).map((row) => row.id),
            [1, 2, 3],
        );
        assert.deepEqual(
            filterBattleLogs(logs, null, true).map((row) => row.id),
            [1, 2, 3],
        );
    });
});
