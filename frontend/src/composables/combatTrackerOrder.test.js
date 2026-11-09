import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    charactersAvailableToAdd,
    isAnonymousParticipant,
    reorderCombatParticipants,
    sortCombatParticipants,
} from './combatTrackerOrder.js';

describe('combatTrackerOrder', () => {
    it('treats a participant without id as anonymous', () => {
        assert.equal(isAnonymousParticipant({ initiative: 3, is_active: true }), true);
        assert.equal(isAnonymousParticipant({ id: null, initiative: 3 }), true);
        assert.equal(isAnonymousParticipant({ id: 4, name: 'А' }), false);
    });

    it('places active participants by descending initiative and inactive ones at the end', () => {
        const sorted = sortCombatParticipants([
            { id: 1, initiative: 1, is_active: true, position: 0 },
            { id: 2, initiative: 100, is_active: false, position: 1 },
            { id: 3, initiative: 9, is_active: true, position: 2 },
            { id: 4, initiative: null, is_active: true, position: 3 },
        ]);

        assert.deepEqual(sorted.map((row) => row.id), [3, 1, 4, 2]);
    });

    it('keeps server order while every initiative is empty', () => {
        const sorted = sortCombatParticipants([
            { id: 9, initiative: null, is_active: true, position: 0 },
            { initiative: null, is_active: true, is_hidden: true, position: 1 },
            { id: 3, initiative: null, is_active: true, position: 2 },
        ]);

        assert.deepEqual(sorted.map((row) => row.position), [0, 1, 2]);
    });

    it('refuses to reorder an anonymous participant', () => {
        const rows = [
            { id: 1, initiative: 5, is_active: true, position: 0 },
            { initiative: 4, is_active: true, position: 1 },
        ];

        assert.equal(reorderCombatParticipants(rows, 1, 0, 0), null);
    });

    it('swaps initiative between the dragged card and the drop target', () => {
        const rows = [
            { id: 1, initiative: 8, is_active: true, position: 0 },
            { id: 2, initiative: 5, is_active: true, position: 1 },
            { id: 3, initiative: 0, is_active: true, position: 2 },
            { id: 4, initiative: 0, is_active: true, position: 3 },
            { id: 5, initiative: 0, is_active: true, position: 4 },
        ];

        const result = reorderCombatParticipants(rows, 2, 1, 0);

        assert.deepEqual(result.participants.map((row) => row.id), [1, 3, 2, 4, 5]);
        assert.deepEqual(result.participants.map((row) => row.initiative), [8, 5, 0, 0, 0]);
        assert.deepEqual(result.changes, [
            { id: 3, initiative: 5 },
            { id: 2, initiative: 0 },
        ]);
        assert.equal(result.currentTurnIndex, 0);
    });

    it('sends zero when both swapped cards already have zero initiative', () => {
        const rows = [
            { id: 1, initiative: 8, is_active: true, position: 0 },
            { id: 2, initiative: 5, is_active: true, position: 1 },
            { id: 3, initiative: 0, is_active: true, position: 2 },
            { id: 4, initiative: 0, is_active: true, position: 3 },
            { id: 5, initiative: 0, is_active: true, position: 4 },
        ];

        const result = reorderCombatParticipants(rows, 4, 2, 1);

        assert.deepEqual(result.participants.map((row) => row.initiative), [8, 5, 0, 0, 0]);
        assert.deepEqual(result.changes, [
            { id: 5, initiative: 0 },
            { id: 3, initiative: 0 },
        ]);
    });

    it('does not swap an active portrait with an inactive one', () => {
        const rows = [
            { id: 1, initiative: 10, is_active: true, position: 0 },
            { id: 2, initiative: 9, is_active: true, position: 1 },
            { id: 3, initiative: 1, is_active: false, position: 2 },
        ];

        assert.equal(reorderCombatParticipants(rows, 0, 2, 0), null);
    });

    it('lists scene characters that are not already in the encounter', () => {
        const available = charactersAvailableToAdd(
            [
                { character_id: 1, character_name: 'А', is_current: true },
                { character_id: 2, character_name: 'Б', is_current: true },
                { character_id: 3, character_name: 'В', is_current: false },
            ],
            [{ id: 10, character_id: 1 }],
        );

        assert.deepEqual(available.map((row) => row.character_id), [2]);
    });
});
