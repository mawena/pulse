<script setup>
import { ref } from 'vue';
import http from '@/lib/http';
import { formatDateTime } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useRealtime } from '@/lib/useRealtime';
import PageHeader from '@/components/PageHeader.vue';

const auth = useAuthStore();
const canRetry = auth.can('manage', 'system');

const tab = ref('pending');
const overview = ref({ pending: [], failed: [], counts: { pending: 0, reserved: 0, failed: 0 } });
const loading = ref(false);
const snackbar = ref({ show: false, text: '', color: 'success' });

const pendingHeaders = [
    { title: 'ID', key: 'id', width: 80 },
    { title: 'Job', key: 'name' },
    { title: 'Queue', key: 'queue', width: 120 },
    { title: 'Tentatives', key: 'attempts', width: 110 },
    { title: 'Statut', key: 'reserved', width: 120 },
    { title: 'Créé', key: 'created_at', width: 170 },
];

const failedHeaders = [
    { title: 'Job', key: 'name' },
    { title: 'Queue', key: 'queue', width: 120 },
    { title: 'Erreur', key: 'exception' },
    { title: 'Échoué le', key: 'failed_at', width: 170 },
    ...(canRetry ? [{ title: '', key: 'actions', sortable: false, width: 70, align: 'end' }] : []),
];

async function fetchJobs() {
    loading.value = true;
    try {
        const { data } = await http.get('/jobs');
        overview.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function retryJob(job) {
    try {
        await http.post(`/jobs/${job.uuid}/retry`);
        snackbar.value = { show: true, text: 'Job relancé — il repasse en file d\'attente.', color: 'success' };
        await fetchJobs();
    } catch {
        snackbar.value = { show: true, text: 'Échec de la relance du job.', color: 'error' };
    }
}

// Temps réel : le backend pousse JobsUpdated (~2 s) sur le canal `jobs`.
useRealtime({
    channel: 'jobs',
    event: 'JobsUpdated',
    immediate: fetchJobs,
    onEvent: (event) => { overview.value = event.overview; },
    poll: fetchJobs,
});
</script>

<template>
    <div>
        <PageHeader
            title="Jobs"
            :subtitle="`${overview.counts.pending} en attente · ${overview.counts.reserved} en cours · ${overview.counts.failed} échoués`"
            icon="mdi-tray-full"
        >
            <v-btn prepend-icon="mdi-refresh" variant="tonal" :loading="loading" @click="fetchJobs">
                Rafraîchir
            </v-btn>
        </PageHeader>

        <v-card>
            <v-tabs v-model="tab" color="primary">
                <v-tab value="pending">
                    En attente
                    <v-chip size="x-small" class="ml-2" variant="tonal">{{ overview.counts.pending }}</v-chip>
                </v-tab>
                <v-tab value="failed">
                    Échoués
                    <v-chip size="x-small" class="ml-2" variant="tonal" :color="overview.counts.failed ? 'error' : undefined">
                        {{ overview.counts.failed }}
                    </v-chip>
                </v-tab>
            </v-tabs>
            <v-divider />

            <v-card-text>
                <v-tabs-window v-model="tab">
                    <v-tabs-window-item value="pending">
                        <v-data-table
                            :headers="pendingHeaders"
                            :items="overview.pending"
                            :loading="loading"
                            density="compact"
                            mobile-breakpoint="sm"
                            hover
                        >
                            <template #item.name="{ value }">
                                <span class="font-data text-caption">{{ value }}</span>
                            </template>
                            <template #item.reserved="{ value }">
                                <v-chip size="x-small" :color="value ? 'info' : undefined">
                                    {{ value ? 'en cours' : 'en attente' }}
                                </v-chip>
                            </template>
                            <template #item.created_at="{ value }">
                                <span class="font-data text-caption">{{ formatDateTime(value) }}</span>
                            </template>
                            <template #no-data>
                                <div class="text-center py-8 text-medium-emphasis">
                                    <v-icon icon="mdi-check-circle-outline" size="40" color="success" class="mb-2" />
                                    <div>Aucun job en attente — la file est vide.</div>
                                </div>
                            </template>
                        </v-data-table>
                    </v-tabs-window-item>

                    <v-tabs-window-item value="failed">
                        <v-data-table
                            :headers="failedHeaders"
                            :items="overview.failed"
                            :loading="loading"
                            density="compact"
                            mobile-breakpoint="sm"
                            hover
                        >
                            <template #item.name="{ value }">
                                <span class="font-data text-caption">{{ value }}</span>
                            </template>
                            <template #item.exception="{ value }">
                                <code class="text-caption d-inline-block text-truncate" style="max-width: 420px">
                                    {{ value.split('\n')[0] }}
                                </code>
                            </template>
                            <template #item.failed_at="{ value }">
                                <span class="font-data text-caption">{{ formatDateTime(value) }}</span>
                            </template>
                            <template v-if="canRetry" #item.actions="{ item }">
                                <v-btn
                                    icon="mdi-restart" size="small" variant="text" color="warning"
                                    title="Relancer le job" @click="retryJob(item)"
                                />
                            </template>
                            <template #no-data>
                                <div class="text-center py-8 text-medium-emphasis">
                                    <v-icon icon="mdi-check-circle-outline" size="40" color="success" class="mb-2" />
                                    <div>Aucun job échoué.</div>
                                </div>
                            </template>
                        </v-data-table>
                    </v-tabs-window-item>
                </v-tabs-window>
            </v-card-text>
        </v-card>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>
