import { onMounted, onUnmounted } from 'vue';
import { getEcho } from '@/lib/echo';

/**
 * Abonne un composant à un canal privé Reverb et applique les événements reçus,
 * avec un filet de sécurité en polling si le WebSocket est indisponible.
 *
 * @param {object}   options
 * @param {string}   options.channel        Nom du canal privé (ex: 'processes')
 * @param {string}   options.event          Nom court de l'événement (ex: 'ProcessesUpdated')
 * @param {Function} options.onEvent        Reçoit le payload de l'événement
 * @param {Function} [options.immediate]    Chargement initial (appelé une fois au montage)
 * @param {Function} [options.poll]         Filet de sécurité (rappelé périodiquement)
 * @param {number}   [options.pollInterval] Intervalle du filet en ms (défaut 30 s)
 */
export function useRealtime({ channel, event, onEvent, immediate, poll, pollInterval = 30000 }) {
    let timer = null;
    let subscribed = false;

    onMounted(() => {
        immediate?.();

        try {
            getEcho().private(channel).listen(event, onEvent);
            subscribed = true;
        } catch {
            // Echo indisponible : on se repose entièrement sur le polling.
        }

        if (poll) timer = setInterval(poll, pollInterval);
    });

    onUnmounted(() => {
        if (subscribed) getEcho().leave(channel);
        clearInterval(timer);
    });
}
