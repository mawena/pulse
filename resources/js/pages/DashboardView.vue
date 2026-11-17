<script setup>
import { computed } from 'vue';
import ApexChart from '@/components/ApexChart.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useMetricsStore } from '@/stores/metrics';
import { formatBytes, formatRate, formatUptime } from '@/lib/format';

// Le flux est démarré par MainLayout (persistant sur toutes les pages) ;
// le dashboard se contente de lire le store.
const metrics = useMetricsStore();

const snap = computed(() => metrics.snapshot);

const baseChartOptions = {
    chart: {
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: false },
        zoom: { enabled: false },
        fontFamily: "'Inter Variable', system-ui, sans-serif",
    },
    theme: { mode: 'dark' },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    grid: { borderColor: '#232F4B', strokeDashArray: 3 },
    tooltip: { theme: 'dark' },
    legend: { labels: { colors: '#8D97B2' } },
};

const percentAxis = {
    min: 0,
    max: 100,
    tickAmount: 4,
    labels: { formatter: (v) => `${v.toFixed(0)}%`, style: { colors: '#8D97B2', fontFamily: "'JetBrains Mono Variable', monospace" } },
};

const cpuChart = computed(() => ({
    options: {
        ...baseChartOptions,
        colors: ['#35E0C2'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
        xaxis: { categories: metrics.history.timestamps, labels: { show: false }, axisTicks: { show: false } },
        yaxis: percentAxis,
    },
    series: [{ name: 'CPU %', data: metrics.history.cpu }],
}));

const memoryChart = computed(() => ({
    options: {
        ...baseChartOptions,
        colors: ['#4D8DFF'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
        xaxis: { categories: metrics.history.timestamps, labels: { show: false }, axisTicks: { show: false } },
        yaxis: percentAxis,
    },
    series: [{ name: 'RAM %', data: metrics.history.memory }],
}));

const networkChart = computed(() => ({
    options: {
        ...baseChartOptions,
        colors: ['#3DDC84', '#FFB454'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
        xaxis: { categories: metrics.history.timestamps, labels: { show: false }, axisTicks: { show: false } },
        yaxis: {
            tickAmount: 4,
            labels: { formatter: (v) => formatRate(v), style: { colors: '#8D97B2', fontFamily: "'JetBrains Mono Variable', monospace" } },
        },
    },
    series: [
        { name: 'Entrant', data: metrics.history.netIn },
        { name: 'Sortant', data: metrics.history.netOut },
    ],
}));

function usageColor(percent) {
    if (percent >= 90) return 'error';
    if (percent >= 70) return 'warning';
    return 'primary';
}

const vitals = computed(() => {
    if (!snap.value) return [];
    const s = snap.value;
    return [
        {
            key: 'cpu',
            label: `CPU · ${s.cpu.cores} cores`,
            icon: 'mdi-chip',
            value: s.cpu.usage_percent ?? '—',
            unit: '%',
            percent: s.cpu.usage_percent ?? 0,
            detail: `Load ${s.cpu.load_1} · ${s.cpu.load_5} · ${s.cpu.load_15}`,
        },
        {
            key: 'ram',
            label: 'Mémoire',
            icon: 'mdi-memory',
            value: s.memory.usage_percent,
            unit: '%',
            percent: s.memory.usage_percent,
            detail: `${formatBytes(s.memory.used)} / ${formatBytes(s.memory.total)}`,
        },
        {
            key: 'swap',
            label: 'Swap',
            icon: 'mdi-swap-horizontal',
            value: s.memory.swap_usage_percent,
            unit: '%',
            percent: s.memory.swap_usage_percent,
            detail: `${formatBytes(s.memory.swap_used)} / ${formatBytes(s.memory.swap_total)}`,
        },
        {
            key: 'net',
            label: 'Réseau',
            icon: 'mdi-swap-vertical-bold',
            custom: true,
        },
    ];
});
</script>

<template>
    <div>
        <PageHeader
            title="Dashboard"
            :subtitle="snap ? `${snap.system.hostname} · ${snap.system.os} · noyau ${snap.system.kernel}` : 'Connexion au serveur…'"
            icon="mdi-view-dashboard-outline"
        >
            <v-chip
                v-if="snap"
                prepend-icon="mdi-clock-outline"
                variant="tonal"
                color="success"
                class="font-data"
            >
                up {{ formatUptime(snap.system.uptime_seconds) }}
            </v-chip>
        </PageHeader>

        <v-alert v-if="metrics.error" type="error" density="compact" class="mb-4">
            {{ metrics.error }} — nouvelle tentative dans quelques secondes.
        </v-alert>

        <template v-if="snap">
            <!-- Signes vitaux -->
            <v-row dense>
                <v-col v-for="vital in vitals" :key="vital.key" cols="6" md="3">
                    <v-card class="pa-4 h-100">
                        <div class="d-flex align-center ga-2 mb-2">
                            <v-icon :icon="vital.icon" size="18" color="primary" />
                            <span class="text-caption text-medium-emphasis text-truncate">{{ vital.label }}</span>
                        </div>

                        <template v-if="!vital.custom">
                            <div class="metric-value">
                                {{ vital.value }}<span class="metric-unit">{{ vital.unit }}</span>
                            </div>
                            <div class="text-caption text-medium-emphasis font-data text-truncate mt-1">
                                {{ vital.detail }}
                            </div>
                            <v-progress-linear
                                :model-value="vital.percent"
                                :color="usageColor(vital.percent)"
                                height="4" rounded class="mt-3"
                            />
                        </template>

                        <template v-else>
                            <div class="d-flex flex-column ga-1 font-data">
                                <div class="d-flex align-center ga-1">
                                    <v-icon icon="mdi-arrow-down-thin" color="success" size="20" />
                                    <span class="metric-value" style="font-size: 1.1rem">{{ formatRate(metrics.netRate.in) }}</span>
                                </div>
                                <div class="d-flex align-center ga-1">
                                    <v-icon icon="mdi-arrow-up-thin" color="warning" size="20" />
                                    <span class="metric-value" style="font-size: 1.1rem">{{ formatRate(metrics.netRate.out) }}</span>
                                </div>
                            </div>
                            <div class="text-caption text-medium-emphasis mt-2">débit instantané</div>
                        </template>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Tracés temps réel -->
            <v-row dense class="mt-1">
                <v-col cols="12" lg="6">
                    <v-card class="pa-1">
                        <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                            <span class="live-dot" />CPU
                        </v-card-title>
                        <v-card-text class="pt-0">
                            <ApexChart type="area" :height="200" :options="cpuChart.options" :series="cpuChart.series" />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" lg="6">
                    <v-card class="pa-1">
                        <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                            <span class="live-dot" />Mémoire
                        </v-card-title>
                        <v-card-text class="pt-0">
                            <ApexChart type="area" :height="200" :options="memoryChart.options" :series="memoryChart.series" />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" lg="6">
                    <v-card class="pa-1">
                        <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                            <span class="live-dot" />Réseau
                        </v-card-title>
                        <v-card-text class="pt-0">
                            <ApexChart type="area" :height="200" :options="networkChart.options" :series="networkChart.series" />
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Disques -->
                <v-col cols="12" lg="6">
                    <v-card class="pa-1 h-100">
                        <v-card-title class="text-subtitle-2">
                            <v-icon icon="mdi-harddisk" size="18" class="mr-2" color="primary" />Disques
                        </v-card-title>
                        <v-card-text>
                            <div v-for="disk in snap.disks" :key="disk.mount" class="mb-4">
                                <div class="d-flex justify-space-between align-baseline text-body-2 mb-1 ga-2">
                                    <span class="font-data text-truncate">{{ disk.mount }}
                                        <span class="text-medium-emphasis text-caption">{{ disk.filesystem }}</span>
                                    </span>
                                    <span class="text-caption text-medium-emphasis font-data text-no-wrap">
                                        {{ formatBytes(disk.available) }} libres
                                    </span>
                                </div>
                                <v-progress-linear
                                    :model-value="disk.usage_percent"
                                    :color="usageColor(disk.usage_percent)"
                                    height="8" rounded
                                />
                                <div class="text-caption text-medium-emphasis font-data mt-1">
                                    {{ formatBytes(disk.used) }} / {{ formatBytes(disk.total) }} ({{ disk.usage_percent }}%)
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </template>

        <v-row v-else dense>
            <v-col v-for="i in 4" :key="i" cols="6" md="3">
                <v-skeleton-loader type="article" height="130" />
            </v-col>
            <v-col cols="12" lg="6"><v-skeleton-loader type="image" height="240" /></v-col>
            <v-col cols="12" lg="6"><v-skeleton-loader type="image" height="240" /></v-col>
        </v-row>
    </div>
</template>
