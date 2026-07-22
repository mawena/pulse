<script setup>
import { onMounted, ref, watch } from 'vue';
import http from '@/lib/http';
import { formatDateTime } from '@/lib/format';

const logs = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const statusFilter = ref(null);
const page = ref(1);
const perPage = ref(15);

const headers = [
    { title: 'Date', key: 'created_at', width: 170 },
    { title: 'Utilisateur', key: 'user', sortable: false },
    { title: 'Action', key: 'action' },
    { title: 'Cible', key: 'target' },
    { title: 'Statut', key: 'status', width: 110 },
    { title: 'IP', key: 'ip_address', width: 130 },
];

const statusColors = { success: 'success', failed: 'error', denied: 'warning' };

async function fetchLogs() {
    loading.value = true;
    try {
        const { data } = await http.get('/audit-logs', {
            params: {
                search: search.value || undefined,
                status: statusFilter.value || undefined, // filtre exact maravel
                page: page.value,
                per_page: perPage.value,
                relation: 'user',
                order_by_desc: 'created_at',
            },
        });
        logs.value = data.data;
        total.value = data.total;
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        fetchLogs();
    }, 400);
});
watch([page, perPage, statusFilter], fetchLogs);

onMounted(fetchLogs);
</script>

<template>
    <div>
        <div class="d-flex align-center mb-4">
            <h1 class="text-h5">Audit Trail</h1>
            <v-spacer />
            <v-btn prepend-icon="mdi-refresh" variant="tonal" :loading="loading" @click="fetchLogs">
                Rafraîchir
            </v-btn>
        </div>

        <v-card>
            <v-card-text>
                <v-row dense class="mb-2">
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="search"
                            prepend-inner-icon="mdi-magnify"
                            label="Rechercher (action, cible, statut)"
                            clearable hide-details
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-select
                            v-model="statusFilter"
                            :items="[
                                { title: 'Tous', value: null },
                                { title: 'Succès', value: 'success' },
                                { title: 'Échec', value: 'failed' },
                                { title: 'Refusé', value: 'denied' },
                            ]"
                            label="Statut" hide-details
                        />
                    </v-col>
                </v-row>

                <v-data-table-server
                    v-model:page="page"
                    v-model:items-per-page="perPage"
                    :headers="headers"
                    :items="logs"
                    :items-length="total"
                    :loading="loading"
                    density="compact"
                    hover
                >
                    <template #item.created_at="{ item }">
                        {{ item.created_at_fr ?? formatDateTime(item.created_at) }}
                    </template>
                    <template #item.user="{ item }">
                        {{ item.user?.name ?? 'Système' }}
                    </template>
                    <template #item.action="{ value }">
                        <code class="text-caption">{{ value }}</code>
                    </template>
                    <template #item.status="{ value }">
                        <v-chip size="x-small" :color="statusColors[value]">{{ value }}</v-chip>
                    </template>
                </v-data-table-server>
            </v-card-text>
        </v-card>
    </div>
</template>
