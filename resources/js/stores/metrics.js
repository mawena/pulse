import { defineStore } from 'pinia';
import http from '@/lib/http';

const HISTORY_SIZE = 30; // ~2min30 d'historique à 5s d'intervalle
export const POLL_INTERVAL_MS = 5000;

/**
 * Store des métriques temps réel : polling de /api/metrics + historique
 * pour les graphiques (CPU, RAM, débit réseau calculé par delta).
 */
export const useMetricsStore = defineStore('metrics', {
    state: () => ({
        snapshot: null,
        history: {
            timestamps: [],
            cpu: [],
            memory: [],
            netIn: [],
            netOut: [],
        },
        lastNetwork: null, // { rxTotal, txTotal, at } — pour le calcul de débit
        netRate: { in: 0, out: 0 },
        error: null,
        _timer: null,
    }),

    actions: {
        async fetch() {
            try {
                const { data } = await http.get('/metrics');
                this.snapshot = data.data;
                this.error = null;
                this._updateNetworkRate();
                this._pushHistory();
            } catch (e) {
                this.error = 'Impossible de récupérer les métriques.';
            }
        },

        startPolling() {
            if (this._timer) return;
            this.fetch();
            this._timer = setInterval(() => this.fetch(), POLL_INTERVAL_MS);
        },

        stopPolling() {
            clearInterval(this._timer);
            this._timer = null;
        },

        /** Débit réseau = delta des compteurs cumulés entre deux pollings. */
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
