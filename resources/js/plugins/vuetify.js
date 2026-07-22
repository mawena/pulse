import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

/**
 * Vuetify 3 — thème sombre par défaut (MawenaPulse).
 */
export default createVuetify({
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: { mdi },
    },
    theme: {
        defaultTheme: 'pulseDark',
        themes: {
            pulseDark: {
                dark: true,
                colors: {
                    background: '#0d1117',
                    surface: '#161b22',
                    'surface-variant': '#21262d',
                    primary: '#3b82f6',
                    secondary: '#8b5cf6',
                    success: '#22c55e',
                    warning: '#f59e0b',
                    error: '#ef4444',
                    info: '#06b6d4',
                },
            },
        },
    },
    defaults: {
        VCard: { elevation: 2, rounded: 'lg' },
        VBtn: { rounded: 'lg' },
        VTextField: { variant: 'outlined', density: 'comfortable' },
    },
});
