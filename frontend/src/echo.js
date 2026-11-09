import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { apiRoot } from './auth';

window.Pusher = Pusher;

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
const reverbHost = import.meta.env.VITE_REVERB_HOST || 'localhost';
// Браузер на хосте. В compose Reverb слушает 8080 и опубликован как 8081:8080.
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || 8081;

/** @type {Echo|null} */
let echoInstance = null;

/**
 * @returns {Echo}
 */
export function createEcho() {
    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: reverbScheme === 'https' ? ['wss'] : ['ws'],
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

    window.Echo = echoInstance;

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }

    window.Echo = null;
}

/**
 * @returns {Echo|null}
 */
export function getEcho() {
    return echoInstance;
}
