import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { apiRoot } from './auth';

window.Pusher = Pusher;

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

/** @type {Echo|null} */
let echoInstance = null;

/**
 * @returns {Echo}
 */
export function createEcho() {
    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                apiRoot.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                    .then((response) => {
                        callback(false, response.data);
                    })
                    .catch((error) => {
                        callback(true, error);
                    });
            },
        }),
    });
}

/**
 * @returns {Echo|null}
 */
export function connectEcho() {
    if (!import.meta.env.VITE_REVERB_APP_KEY) {
        return null;
    }

    if (!echoInstance) {
        echoInstance = createEcho();
    }

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

/**
 * @returns {Echo|null}
 */
export function getEcho() {
    return echoInstance;
}
