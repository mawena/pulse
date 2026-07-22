import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import http from '@/lib/http';

window.Pusher = Pusher;

let echo = null;

/**
 * Client WebSocket (Laravel Reverb) — instance paresseuse partagée.
 * L'autorisation des canaux privés passe par POST /api/broadcasting/auth
 * avec le token Sanctum (via l'instance axios centralisée).
 */
export function getEcho() {
    if (echo) return echo;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                http.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error));
            },
        }),
    });

    return echo;
}

/** Ferme la connexion (au logout). */
export function disconnectEcho() {
    echo?.disconnect();
    echo = null;
}
