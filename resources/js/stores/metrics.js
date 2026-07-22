import { defineStore } from 'pinia';
import http from '@/lib/http';
import { getEcho } from '@/lib/echo';

const HISTORY_SIZE = 120; // ~1min d'historique à 0.5s (WS) — 10min à 5s (polling)
export const POLL_INTERVAL_MS = 5000;

/**
 * Store des métriques temps réel.
 *
 * Transport principal : WebSocket (Reverb) — le backend échantillonne via
 * `php artisan pulse:stream` et pousse les snapshots sur le canal `metrics`.
 * Fallback automatique : polling HTTP de /api/metrics si le WS est
 * indisponible (Reverb éteint, proxy sans websocket…), avec retour au WS
 * dès qu'il redevient joignable.
 */
export const useMetricsStore = defineStore('metrics', {
    state: () => ({
        snapshot: null,
        transport: 'connecting', // connecting | ws | polling
        history: {
            timestamps: [],
            cpu: [],
            memory: [],
            netIn: [],
            netOut: [],
        },
        lastNetwork: null,
        netRate: { in: 0, out: 0 },
        error: null,
        _timer: null,
        _channel: null,
    }),

    actions: {
        /** Point d'entrée : tente le WebSocket, arme le fallback HTTP. */
        start() {
            if (this._channel) return;

            // Premier snapshot immédiat en HTTP (sans attendre le 1er tick WS)
            this.fetch();

            const echo = getEcho();
            this._channel = echo.private('metrics').listen('MetricsUpdated', (event) => {
                this._apply(event.snapshot);
            });

            const connection = echo.connector.pusher.connection;
            connection.bind('connected', () => {
                this.transport = 'ws';
                this._stopPollingTimer();
            });
            connection.bind('unavailable', () => this._fallbackToPolling());
            connection.bind('failed', () => this._fallbackToPolling());
            connection.bind('disconnected', () => {
                if (this.transport === 'ws') this._fallbackToPolling();
            });

            // Si le WS n'est pas établi rapidement, on polle en attendant.
            setTimeout(() => {
                if (this.transport !== 'ws') this._fallbackToPolling();
            }, 3000);
        },

        stop() {
            this._stopPollingTimer();
            if (this._channel) {
                getEcho().leave('metrics');
                this._channel = null;
            }
        },

        async fetch() {
            try {
                const { data } = await http.get('/metrics');
                this._apply(data.data);
            } catch {
                this.error = 'Impossible de récupérer les métriques.';
            }
        },

        _apply(snapshot) {
            this.snapshot = snapshot;
            this.error = null;
            this._updateNetworkRate();
            this._pushHistory();
        },

        _fallbackToPolling() {
            if (this._timer) return;
            this.transport = 'polling';
            this._timer = setInterval(() => this.fetch(), POLL_INTERVAL_MS);
        },

        _stopPollingTimer() {
            clearInterval(this._timer);
            this._timer = null;
        },

        /** Débit réseau = delta des compteurs cumulés entre deux snapshots. */
        _updateNetworkRate() {
            const now = Date.now();
            const rxTotal = this.snapshot.network.reduce((sum, i) => sum + i.rx_bytes, 0);
            const txTotal = this.snapshot.network.reduce((sum, i) => sum + i.tx_bytes, 0);

            if (this.lastNetwork) {
                const seconds = (now - this.lastNetwork.at) / 1000;
                if (seconds > 0) {
                    this.netRate = {
                        in: Math.max(0, (rxTotal - this.lastNetwork.rxTotal) / seconds),
                        out: Math.max(0, (txTotal - this.lastNetwork.txTotal) / seconds),
                    };
                }
            }
            this.lastNetwork = { rxTotal, txTotal, at: now };
        },

        _pushHistory() {
            const h = this.history;
            h.timestamps.push(new Date(this.snapshot.timestamp).toLocaleTimeString('fr-FR'));
            h.cpu.push(this.snapshot.cpu.usage_percent ?? 0);
            h.memory.push(this.snapshot.memory.usage_percent);
            h.netIn.push(Math.round(this.netRate.in));
            h.netOut.push(Math.round(this.netRate.out));

            for (const key of Object.keys(h)) {
                if (h[key].length > HISTORY_SIZE) h[key].shift();
            }
        },
    },
});
