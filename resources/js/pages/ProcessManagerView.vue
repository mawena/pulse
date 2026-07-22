<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import http from '@/lib/http';
import { formatBytes } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import PageHeader from '@/components/PageHeader.vue';

const auth = useAuthStore();
const canKill = auth.can('manage', 'system');

const processes = ref([]);
const loading = ref(false);
const search = ref('');
const autoRefresh = ref(true);
const snackbar = ref({ show: false, text: '', color: 'success' });
let timer = null;

const killDialog = ref({ show: false, process: null, signal: 'TERM', loading: false });

const headers = [
    { title: 'PID', key: 'pid', width: 90 },
    { title: 'Utilisateur', key: 'user', width: 140 },
    { title: 'CPU %', key: 'cpu_percent', width: 100 },
    { title: 'RAM %', key: 'memory_percent', width: 100 },
    { title: 'Mémoire', key: 'rss', width: 110 },
    { title: 'État', key: 'state', width: 80 },
    { title: 'Durée', key: 'elapsed', width: 110 },
    { title: 'Commande', key: 'command' },
    ...(canKill ? [{ title: '', key: 'actions', sortable: false, width: 60, align: 'end' }] : []),
];

async function fetchProcesses() {
    loading.value = true;
    try {
        const { data } = await http.get('/processes');
        processes.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openKillDialog(process) {
    killDialog.value = { show: true, process, signal: 'TERM', loading: false };
}

async function confirmKill() {
    killDialog.value.loading = true;
    try {
        await http.post('/processes/kill', {
            pid: killDialog.value.process.pid,
            signal: killDialog.value.signal,
        });
        snackbar.value = {
            show: true,
            text: `Processus ${killDialog.value.process.pid} arrêté.`,
            color: 'success',
        };
        killDialog.value.show = false;
        await fetchProcesses();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? {}).flat().join(' ') || 'Échec du kill.',
            color: 'error',
        };
    } finally {
        killDialog.value.loading = false;
    }
}

onMounted(() => {
    fetchProcesses();
    timer = setInterval(() => {
        if (autoRefresh.value && !killDialog.value.show) fetchProcesses();
    }, 10000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div>
        <PageHeader
            title="Processus"
            :subtitle="`${processes.length} processus actifs, triés par CPU`"
            icon="mdi-memory"
        >
            <v-switch
                v-model="autoRefresh" label="Auto (10s)"
                density="compact" hide-details color="primary"
            />
            <v-btn prepend-icon="mdi-refresh" variant="tonal" :loading="loading" @click="fetchProcesses">
                Rafraîchir
            </v-btn>
        </PageHeader>

        <v-card>
            <v-card-text>
                <v-text-field
                    v-model="search"
                    prepend-inner-icon="mdi-magnify"
                    label="Rechercher (PID, utilisateur, commande…)"
                    clearable hide-details class="mb-4"
                />
                <v-data-table
                    :headers="headers"
                    :items="processes"
                    :search="search"
                    :loading="loading"
                    :items-per-page="25"
                    density="compact"
                    mobile-breakpoint="sm"
                    hover
                >
                    <template #item.cpu_percent="{ value }">
                        <v-chip size="x-small" :color="value > 50 ? 'error' : value > 20 ? 'warning' : undefined">
                            {{ value.toFixed(1) }}
                        </v-chip>
                    </template>
                    <template #item.memory_percent="{ value }">
                        {{ value.toFixed(1) }}
                    </template>
                    <template #item.rss="{ value }">
                        {{ formatBytes(value) }}
                    </template>
                    <template #item.pid="{ value }">
                        <span class="font-data">{{ value }}</span>
                    </template>
                    <template #item.command="{ value }">
                        <span class="text-caption font-data">{{ value.slice(0, 120) }}</span>
                    </template>
                    <template v-if="canKill" #item.actions="{ item }">
                        <v-btn
                            icon="mdi-close-octagon" size="small" variant="text" color="error"
                            title="Tuer le processus" @click="openKillDialog(item)"
                        />
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <!-- Modal de confirmation kill -->
        <v-dialog v-model="killDialog.show" max-width="480">
            <v-card v-if="killDialog.process">
                <v-card-title class="text-error">
                    <v-icon icon="mdi-alert" class="mr-2" />Tuer le processus ?
                </v-card-title>
                <v-card-text>
                    <p class="mb-3">
                        Vous êtes sur le point d'envoyer un signal au processus
                        <strong>PID {{ killDialog.process.pid }}</strong>
                        ({{ killDialog.process.user }}) :
                    </p>
                    <code class="d-block pa-2 mb-4 bg-surface-variant rounded text-caption">
                        {{ killDialog.process.command.slice(0, 200) }}
                    </code>
                    <v-radio-group v-model="killDialog.signal" hide-details>
                        <v-radio label="SIGTERM — arrêt propre (recommandé)" value="TERM" />
                        <v-radio label="SIGKILL — arrêt forcé immédiat" value="KILL" />
                    </v-radio-group>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="killDialog.show = false">Annuler</v-btn>
                    <v-btn color="error" variant="flat" :loading="killDialog.loading" @click="confirmKill">
                        Tuer le processus
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>
