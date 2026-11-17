<script setup>
import { computed, ref } from 'vue';
import http from '@/lib/http';
import { useRealtime } from '@/lib/useRealtime';
import PageHeader from '@/components/PageHeader.vue';

const services = ref([]);
const loading = ref(false);
const search = ref('');
const stateFilter = ref(null);

const headers = [
    { title: 'Unité', key: 'unit' },
    { title: 'Description', key: 'description' },
    { title: 'Chargement', key: 'load', width: 120 },
    { title: 'État', key: 'active', width: 110 },
    { title: 'Détail', key: 'sub', width: 120 },
];

const filtered = computed(() => {
    let list = services.value;
    if (stateFilter.value === 'running') list = list.filter((s) => s.running);
    if (stateFilter.value === 'failed') list = list.filter((s) => s.failed);
    if (stateFilter.value === 'inactive') list = list.filter((s) => !s.running && !s.failed);
    return list;
});

const counts = computed(() => ({
    total: services.value.length,
    running: services.value.filter((s) => s.running).length,
    failed: services.value.filter((s) => s.failed).length,
}));

function activeColor(service) {
    if (service.failed) return 'error';
    return service.running ? 'success' : undefined;
}

async function fetchServices() {
    loading.value = true;
    try {
        const { data } = await http.get('/system-services');
        services.value = data.data;
    } finally {
        loading.value = false;
    }
}

// Temps réel : le backend pousse ServicesUpdated (~15 s) sur le canal
// `services` — la liste systemd complète est dans le champ `services`.
useRealtime({
    channel: 'services',
    event: 'ServicesUpdated',
    immediate: fetchServices,
    onEvent: (event) => { services.value = event.services; },
    poll: fetchServices,
});
</script>

<template>
    <div>
        <PageHeader
            title="Services système"
            :subtitle="`${counts.total} unités systemd — ${counts.running} actives, ${counts.failed} en échec`"
            icon="mdi-cog-outline"
        >
            <v-btn prepend-icon="mdi-refresh" variant="tonal" :loading="loading" @click="fetchServices">
                Rafraîchir
            </v-btn>
        </PageHeader>

        <v-alert
            v-if="counts.failed > 0"
            type="error" variant="tonal" density="compact" class="mb-4"
            :text="`${counts.failed} service(s) en échec — vérifiez les journaux systemd.`"
        />

        <v-card>
            <v-card-text>
                <v-row dense class="mb-2">
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="search"
                            prepend-inner-icon="mdi-magnify"
                            label="Rechercher une unité ou une description"
                            clearable hide-details
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-select
                            v-model="stateFilter"
                            :items="[
                                { title: 'Tous', value: null },
                                { title: 'Actifs (running)', value: 'running' },
                                { title: 'En échec', value: 'failed' },
                                { title: 'Inactifs', value: 'inactive' },
                            ]"
                            label="État" hide-details
                        />
                    </v-col>
                </v-row>

                <v-data-table
                    :headers="headers"
                    :items="filtered"
                    :search="search"
                    :loading="loading"
                    :items-per-page="25"
                    density="compact"
                    mobile-breakpoint="sm"
                    hover
                >
                    <template #item.unit="{ value }">
                        <span class="font-data text-caption">{{ value }}</span>
                    </template>
                    <template #item.load="{ value }">
                        <v-chip size="x-small" :color="value === 'loaded' ? undefined : 'warning'">
                            {{ value }}
                        </v-chip>
                    </template>
                    <template #item.active="{ item }">
                        <v-chip size="x-small" :color="activeColor(item)">
                            {{ item.active }}
                        </v-chip>
                    </template>
                    <template #item.sub="{ value }">
                        <span class="text-caption text-medium-emphasis">{{ value }}</span>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>
    </div>
</template>
