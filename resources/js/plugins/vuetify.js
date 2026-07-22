import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

/**
 * Vuetify 3 — thème « moniteur de signes vitaux » (MawenaPulse).
 * Fond bleu-nuit, panneaux bordés sans ombres, accent teal ECG,
 * statuts sémantiques bien distincts de l'accent.
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
                    background: '#0B1020',
                    surface: '#121A2F',
                    'surface-variant': '#182238',
                    'on-surface-variant': '#8D97B2',
                    primary: '#35E0C2',
                    'on-primary': '#06251F',
                    secondary: '#4D8DFF',
                    success: '#3DDC84',
                    warning: '#FFB454',
                    error: '#FF5D5D',
                    info: '#4D8DFF',
                },
                variables: {
                    'border-color': '#232F4B',
                    'border-opacity': 1,
                    'medium-emphasis-opacity': 0.62,
                },
            },
        },
    },
    defaults: {
        VCard: { elevation: 0, rounded: 'lg' },
        VBtn: { rounded: 'lg', style: 'text-transform: none; letter-spacing: 0;' },
        VTextField: { variant: 'outlined', density: 'comfortable' },
        VSelect: { variant: 'outlined', density: 'comfortable' },
        VChip: { rounded: 'lg' },
        VAlert: { rounded: 'lg' },
        VDialog: { scrim: '#060A16' },
        VDataTable: { hover: true },
        VDataTableServer: { hover: true },
    },
})
