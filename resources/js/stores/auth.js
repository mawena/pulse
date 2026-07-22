import { defineStore } from 'pinia';
import http from '@/lib/http';

/**
 * Store d'authentification — token Sanctum + utilisateur courant
 * (avec ses ability_rules CASL calculées par Maravel).
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('pulse_token'),
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        abilityRules: (state) => state.user?.ability_rules ?? [],
        /** Vérifie une permission CASL (action / subject) côté client. */
        can: (state) => (action, subject) =>
            (state.user?.ability_rules ?? []).some(
                (rule) =>
                    (rule.action === action || rule.action === 'manage') &&
                    (rule.subject === subject || rule.subject === 'all'),
            ),
    },

    actions: {
        async login(email, password) {
            this.loading = true;
            try {
                // Format Maravel : { status, data: { userToken, user }, messages }
                const { data } = await http.post('/auth/login', { email, password });
                this.token = data.data.userToken;
                this.user = data.data.user;
                localStorage.setItem('pulse_token', this.token);
            } finally {
                this.loading = false;
            }
        },

        async fetchUser() {
            const { data } = await http.get('/auth/data');
            this.user = data.data;
        },

        async logout() {
            try {
                await http.delete('/auth/logout');
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('pulse_token');
            }
        },
    },
});
