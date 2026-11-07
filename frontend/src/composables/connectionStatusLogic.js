export const CONNECTION_LOST_DELAY_MS = 5000;

const UNHEALTHY_STATES = new Set(['unavailable', 'failed']);

/**
 * @param {string} state
 */
export function isUnhealthyConnectionState(state) {
    return UNHEALTHY_STATES.has(state);
}

/**
 * @param {string} state
 */
export function isHealthyConnectionState(state) {
    return state === 'connected';
}

/**
 * @typedef {{ setTimeout: (fn: () => void, ms: number) => unknown, clearTimeout: (id: unknown) => void }} Scheduler
 * @typedef {(event: 'connection_lost' | 'connection_restored') => void} EmitFn
 */

/**
 * @param {{ delayMs?: number, schedule: Scheduler, emit: EmitFn }} options
 */
export function createConnectionStatusTracker({
    delayMs = CONNECTION_LOST_DELAY_MS,
    schedule,
    emit,
}) {
    /** @type {unknown|null} */
    let unhealthyTimer = null;
    let fallbackActive = false;

    function clearUnhealthyTimer() {
        if (unhealthyTimer !== null) {
            schedule.clearTimeout(unhealthyTimer);
            unhealthyTimer = null;
        }
    }

    function enterFallback() {
        if (fallbackActive) {
            return;
        }

        fallbackActive = true;
        emit('connection_lost');
    }

    function leaveFallback() {
        if (!fallbackActive) {
            return;
        }

        fallbackActive = false;
        emit('connection_restored');
    }

    /**
     * @param {string} currentState
     */
    function onConnectionStateChange(currentState) {
        if (isHealthyConnectionState(currentState)) {
            clearUnhealthyTimer();
            leaveFallback();

            return;
        }

        if (unhealthyTimer !== null) {
            return;
        }

        unhealthyTimer = schedule.setTimeout(() => {
            unhealthyTimer = null;
            enterFallback();
        }, delayMs);
    }

    function dispose() {
        clearUnhealthyTimer();
    }

    return {
        onConnectionStateChange,
        dispose,
        isFallbackActive: () => fallbackActive,
    };
}
