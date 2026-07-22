import { defineStore } from 'pinia';
import http from '@/lib/http';
import { disconnectEcho } from '@/lib/echo';

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
        /**
         * Vérifie une permission CASL (action / subject) côté client.
         * Les règles maravel ont des action/subject en TABLEAUX :
         * [{ subject: ['all'], action: ['manage'] }] — on normalise les deux formes.
         */
        can: (state) => (action, subject) =>
            (state.user?.ability_rules ?? []).some((rule) => {
                const subjects = [rule.subject ?? []].flat();
                const actions = [rule.action ?? []].flat();
                return (
                    (subjects.includes('all') || subjects.includes(subject)) &&
                    (actions.includes('manage') || actions.includes(action))
                );
            }),
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

        /**
         * Changement de mot de passe (obligatoire à la 1ère connexion quand
         * password_change_required est vrai — seule route autorisée par le
         * middleware account.status avec logout).
         */
        async updatePassword(currentPassword, newPassword, confirmation) {
            this.loading = true;
            try {
                await http.put('/users/update-password', {
                    current_password: currentPassword,
                    new_password: newPassword,
                    new_password_confirmation: confirmation,
                });
                if (this.user) this.user.password_change_required = false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await http.delete('/auth/logout');
            } finally {
                disconnectEcho();
                this.token = null;
                this.user = null;
                localStorage.removeItem('pulse_token');
            }
        },
    },
});
