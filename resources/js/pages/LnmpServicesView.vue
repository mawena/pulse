<script setup>
import { ref } from 'vue';
import http from '@/lib/http';
import { useAuthStore } from '@/stores/auth';
import { useRealtime } from '@/lib/useRealtime';
import PageHeader from '@/components/PageHeader.vue';

const auth = useAuthStore();
const canManage = auth.can('manage', 'system');

const services = ref([]);
const loading = ref(false);
const snackbar = ref({ show: false, text: '', color: 'success' });

const confirmDialog = ref({ show: false, service: null, action: null, loading: false });

const icons = {
    nginx: 'mdi-web',
    mysql: 'mdi-database',
    'php-fpm': 'mdi-language-php',
};

async function fetchStatus() {
    loading.value = true;
    try {
        const { data } = await http.get('/lnmp');
        services.value = data.data;
    } finally {
        loading.value = false;
    }
}

function askConfirm(service, action) {
    confirmDialog.value = { show: true, service, action, loading: false };
}

async function runAction() {
    confirmDialog.value.loading = true;
    const { service, action } = confirmDialog.value;
    try {
        await http.post('/lnmp/action', { service: service.key, action });
        snackbar.value = { show: true, text: `${action} exécuté sur ${service.key}.`, color: 'success' };
        confirmDialog.value.show = false;
        await fetchStatus();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? {}).flat().join(' ') || `Échec du ${action}.`,
            color: 'error',
        };
    } finally {
        confirmDialog.value.loading = false;
    }
}

// Temps réel : le backend pousse ServicesUpdated (~15 s) sur le canal
// `services` — l'état LNMP est dans le champ `lnmp`.
useRealtime({
    channel: 'services',
    event: 'ServicesUpdated',
    immediate: fetchStatus,
    onEvent: (event) => { services.value = event.lnmp; },
    poll: fetchStatus,
});
</script>

<template>
    <div>
        <PageHeader
            title="Services LNMP"
            subtitle="nginx, MySQL et PHP-FPM de la stack mawena/lnmp"
            icon="mdi-server-network"
        >
            <v-btn prepend-icon="mdi-refresh" variant="tonal" :loading="loading" @click="fetchStatus">
                Rafraîchir
            </v-btn>
        </PageHeader>

        <v-row dense>
            <v-col v-for="service in services" :key="service.key" cols="12" md="4">
                <v-card>
                    <v-card-item>
                        <template #prepend>
                            <v-avatar :color="service.running ? 'success' : 'error'" variant="tonal">
                                <v-icon :icon="icons[service.key] ?? 'mdi-cog'" />
                            </v-avatar>
                        </template>
                        <v-card-title>{{ service.key }}</v-card-title>
                        <v-card-subtitle>{{ service.unit }}</v-card-subtitle>
                    </v-card-item>
                    <v-card-text>
                        <v-chip
                            :color="service.running ? 'success' : 'error'"
                            :prepend-icon="service.running ? 'mdi-check-circle' : 'mdi-alert-circle'"
                            size="small" class="mb-2"
                        >
                            {{ service.active_state }} ({{ service.sub_state }})
                        </v-chip>
                        <div v-if="service.since" class="text-caption text-medium-emphasis">
                            Démarré : {{ service.since }}
                        </div>
                    </v-card-text>
                    <v-card-actions v-if="canManage">
                        <v-btn
                            prepend-icon="mdi-restart" color="warning" variant="tonal" size="small"
                            @click="askConfirm(service, 'restart')"
                        >
                            Restart
                        </v-btn>
                        <v-btn
                            prepend-icon="mdi-reload" color="info" variant="tonal" size="small"
                            @click="askConfirm(service, 'reload')"
                        >
                            Reload
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>

        <v-skeleton-loader v-if="loading && !services.length" type="card@3" />

        <!-- Modal de confirmation -->
        <v-dialog v-model="confirmDialog.show" max-width="440">
            <v-card v-if="confirmDialog.service">
                <v-card-title class="text-warning">
                    <v-icon icon="mdi-alert" class="mr-2" />Confirmer l'action
                </v-card-title>
                <v-card-text>
                    Exécuter <strong>{{ confirmDialog.action }}</strong> sur le service
                    <strong>{{ confirmDialog.service.key }}</strong>
                    ({{ confirmDialog.service.unit }}) ?
                    <v-alert
                        v-if="confirmDialog.action === 'restart'"
                        type="warning" variant="tonal" density="compact" class="mt-3"
                    >
                        Un restart interrompt brièvement le service.
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="confirmDialog.show = false">Annuler</v-btn>
                    <v-btn color="warning" variant="flat" :loading="confirmDialog.loading" @click="runAction">
                        Confirmer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>
