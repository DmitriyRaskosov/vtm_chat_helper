import { onUnmounted, ref } from 'vue';

import { getEcho } from '../echo';

import {
    CONNECTION_LOST_DELAY_MS,
    createConnectionStatusTracker,
} from './connectionStatusLogic';

/**
 * @typedef {(handler: () => void) => () => void} RegisterHandler
 */

/**
 * @returns {{
 *   isFallbackMode: import('vue').Ref<boolean>,
 *   onConnectionLost: RegisterHandler,
 *   onConnectionRestored: RegisterHandler,
 *   startMonitoring: () => void,
 * }}
 */
export function useConnectionStatus() {
    const isFallbackMode = ref(false);
    /** @type {Set<() => void>} */
    const lostHandlers = new Set();
    /** @type {Set<() => void>} */
    const restoredHandlers = new Set();

    /** @type {ReturnType<typeof createConnectionStatusTracker>|null} */
    let tracker = null;
    /** @type {(() => void)|null} */
    let unbindStateChange = null;

    function emitLost() {
        isFallbackMode.value = true;
        for (const handler of lostHandlers) {
            handler();
        }
    }

    function emitRestored() {
        isFallbackMode.value = false;
        for (const handler of restoredHandlers) {
            handler();
        }
    }

    function onConnectionLost(handler) {
        lostHandlers.add(handler);

        return () => {
            lostHandlers.delete(handler);
        };
    }

    function onConnectionRestored(handler) {
        restoredHandlers.add(handler);

        return () => {
            restoredHandlers.delete(handler);
        };
    }

    function bindConnection(connection) {
        unbindStateChange?.();
        unbindStateChange = null;

        tracker?.dispose();
        tracker = createConnectionStatusTracker({
            delayMs: CONNECTION_LOST_DELAY_MS,
            schedule: {
                setTimeout: (fn, ms) => window.setTimeout(fn, ms),
                clearTimeout: (id) => window.clearTimeout(id),
            },
            emit: (event) => {
                if (event === 'connection_lost') {
                    emitLost();
                } else {
                    emitRestored();
                }
            },
        });

        const handler = (states) => {
            tracker?.onConnectionStateChange(states.current);
        };

        connection.bind('state_change', handler);
        tracker.onConnectionStateChange(connection.state);

        unbindStateChange = () => {
            connection.unbind('state_change', handler);
        };
    }

    function startMonitoring() {
        if (!import.meta.env.VITE_REVERB_APP_KEY) {
            emitLost();

            return;
        }

        const echo = getEcho();
        const connection = echo?.connector?.pusher?.connection;

        if (!connection) {
            emitLost();

            return;
        }

        bindConnection(connection);
    }

    onUnmounted(() => {
        unbindStateChange?.();
        tracker?.dispose();
        lostHandlers.clear();
        restoredHandlers.clear();
    });

    return {
        isFallbackMode,
        onConnectionLost,
        onConnectionRestored,
        startMonitoring,
    };
}
