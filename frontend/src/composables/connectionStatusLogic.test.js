import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    CONNECTION_LOST_DELAY_MS,
    createConnectionStatusTracker,
    isHealthyConnectionState,
    isUnhealthyConnectionState,
} from './connectionStatusLogic.js';

describe('connectionStatusLogic', () => {
    it('classifies pusher states', () => {
        assert.equal(isUnhealthyConnectionState('unavailable'), true);
        assert.equal(isUnhealthyConnectionState('failed'), true);
        assert.equal(isUnhealthyConnectionState('connecting'), false);
        assert.equal(isHealthyConnectionState('connected'), true);
    });

    it('emits connection_lost after delay on unhealthy state', () => {
        const events = [];
        let timerId = 1;
        const timeouts = new Map();

        const tracker = createConnectionStatusTracker({
            delayMs: CONNECTION_LOST_DELAY_MS,
            schedule: {
                setTimeout: (fn, ms) => {
                    assert.equal(ms, CONNECTION_LOST_DELAY_MS);
                    const id = timerId;
                    timerId += 1;
                    timeouts.set(id, fn);

                    return id;
                },
                clearTimeout: (id) => {
                    timeouts.delete(id);
                },
            },
            emit: (event) => {
                events.push(event);
            },
        });

        tracker.onConnectionStateChange('unavailable');
        assert.deepEqual(events, []);

        timeouts.get(1)?.();
        assert.deepEqual(events, ['connection_lost']);
        assert.equal(tracker.isFallbackActive(), true);
    });

    it('emits connection_restored when connected after fallback', () => {
        const events = [];
        const timeouts = new Map();
        let timerId = 1;

        const tracker = createConnectionStatusTracker({
            delayMs: 100,
            schedule: {
                setTimeout: (fn) => {
                    const id = timerId;
                    timerId += 1;
                    timeouts.set(id, fn);

                    return id;
                },
                clearTimeout: (id) => {
                    timeouts.delete(id);
                },
            },
            emit: (event) => {
                events.push(event);
            },
        });

        tracker.onConnectionStateChange('failed');
        timeouts.get(1)?.();
        tracker.onConnectionStateChange('connected');

        assert.deepEqual(events, ['connection_lost', 'connection_restored']);
        assert.equal(tracker.isFallbackActive(), false);
    });

    it('schedules lost timer for prolonged connecting state', () => {
        const events = [];
        const timeouts = new Map();
        let timerId = 1;

        const tracker = createConnectionStatusTracker({
            delayMs: 100,
            schedule: {
                setTimeout: (fn) => {
                    const id = timerId;
                    timerId += 1;
                    timeouts.set(id, fn);

                    return id;
                },
                clearTimeout: () => {
                    timeouts.clear();
                },
            },
            emit: (event) => {
                events.push(event);
            },
        });

        tracker.onConnectionStateChange('connecting');
        timeouts.get(1)?.();

        assert.deepEqual(events, ['connection_lost']);
    });

    it('cancels pending lost timer when connection recovers in time', () => {
        const events = [];
        const timeouts = new Map();
        let timerId = 1;

        const tracker = createConnectionStatusTracker({
            delayMs: CONNECTION_LOST_DELAY_MS,
            schedule: {
                setTimeout: (fn) => {
                    const id = timerId;
                    timerId += 1;
                    timeouts.set(id, fn);

                    return id;
                },
                clearTimeout: () => {
                    timeouts.clear();
                },
            },
            emit: (event) => {
                events.push(event);
            },
        });

        tracker.onConnectionStateChange('unavailable');
        tracker.onConnectionStateChange('connected');

        assert.deepEqual(events, []);
    });
});
