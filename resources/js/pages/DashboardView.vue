<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import ApexChart from '@/components/ApexChart.vue';
import { useMetricsStore } from '@/stores/metrics';
import { formatBytes, formatRate, formatUptime } from '@/lib/format';

const metrics = useMetricsStore();

onMounted(() => metrics.startPolling());
onUnmounted(() => metrics.stopPolling());

const snap = computed(() => metrics.snapshot);

const baseChartOptions = {
    chart: {
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: false },
        zoom: { enabled: false },
    },
    theme: { mode: 'dark' },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    grid: { borderColor: '#30363d' },
    tooltip: { theme: 'dark' },
};

const cpuChart = computed(() => ({
    options: {
        ...baseChartOptions,
        colors: ['#3b82f6'],
        xaxis: { categories: metrics.history.timestamps, labels: { show: false } },
        yaxis: { min: 0, max: 100, labels: { formatter: (v) => `${v.toFixed(0)}%` } },
    },
    series: [{ name: 'CPU %', data: metrics.history.cpu }],
}));

const memoryChart = computed(() => ({
    options: {
        ...baseChartOptions,
        colors: ['#8b5cf6'],
        xaxis: { categories: metrics.history.timestamps, labels: { show: false } },
        yaxis: { min: 0, max: 100, labels: { formatter: (v) => `${v.toFixed(0)}%` } },
    },
    series: [{ name: 'RAM %', data: metrics.history.memory }],
}));

const networkChart = computed(() => ({
    options: {
        ...baseChartOptions,
        chart: { ...baseChartOptions.chart, type: 'area' },
        colors: ['#22c55e', '#f59e0b'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: metrics.history.timestamps, labels: { show: false } },
        yaxis: { labels: { formatter: (v) => formatRate(v) } },
    },
    series: [
        { name: 'IN', data: metrics.history.netIn },
        { name: 'OUT', data: metrics.history.netOut },
    ],
}));

function usageColor(percent) {
    if (percent >= 90) return 'error';
    if (percent >= 70) return 'warning';
    return 'success';
}
</script>

<template>
    <div>
        <div class="d-flex align-center mb-4">
            <h1 class="text-h5">Dashboard</h1>
            <v-spacer />
            <v-chip v-if="snap" prepend-icon="mdi-server" variant="tonal" class="mr-2">
                {{ snap.system.hostname }} · {{ snap.system.os }} · {{ snap.system.kernel }}
            </v-chip>
            <v-chip v-if="snap" prepend-icon="mdi-clock-outline" variant="tonal" color="success">
                up {{ formatUptime(snap.system.uptime_seconds) }}
            </v-chip>
        </div>

        <v-alert v-if="metrics.error" type="error" density="compact" class="mb-4">
            {{ metrics.error }}
        </v-alert>

        <template v-if="snap">
            <!-- Cartes de synthèse -->
            <v-row dense>
                <v-col cols="12" sm="6" md="3">
                    <v-card>
                        <v-card-item>
                            <v-card-subtitle>CPU ({{ snap.cpu.cores }} cores)</v-card-subtitle>
                            <div class="text-h4 my-1">
                                {{ snap.cpu.usage_percent ?? '—' }}<span class="text-h6">%</span>
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                Load : {{ snap.cpu.load_1 }} / {{ snap.cpu.load_5 }} / {{ snap.cpu.load_15 }}
                            </div>
                            <v-progress-linear
                                :model-value="snap.cpu.usage_percent ?? 0"
                                :color="usageColor(snap.cpu.usage_percent ?? 0)"
                                height="6" rounded class="mt-2"
                            />
                        </v-card-item>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card>
                        <v-card-item>
                            <v-card-subtitle>RAM</v-card-subtitle>
                            <div class="text-h4 my-1">
                                {{ snap.memory.usage_percent }}<span class="text-h6">%</span>
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ formatBytes(snap.memory.used) }} / {{ formatBytes(snap.memory.total) }}
                                · cache {{ formatBytes(snap.memory.cached) }}
                            </div>
                            <v-progress-linear
                                :model-value="snap.memory.usage_percent"
                                :color="usageColor(snap.memory.usage_percent)"
                                height="6" rounded class="mt-2"
                            />
                        </v-card-item>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card>
                        <v-card-item>
                            <v-card-subtitle>Swap</v-card-subtitle>
                            <div class="text-h4 my-1">
                                {{ snap.memory.swap_usage_percent }}<span class="text-h6">%</span>
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ formatBytes(snap.memory.swap_used) }} / {{ formatBytes(snap.memory.swap_total) }}
                            </div>
                            <v-progress-linear
                                :model-value="snap.memory.swap_usage_percent"
                                :color="usageColor(snap.memory.swap_usage_percent)"
                                height="6" rounded class="mt-2"
                            />
                        </v-card-item>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card>
                        <v-card-item>
                            <v-card-subtitle>Réseau</v-card-subtitle>
                            <div class="text-h6 my-1">
                                <v-icon icon="mdi-arrow-down" color="success" size="small" />
                                {{ formatRate(metrics.netRate.in) }}
                            </div>
                            <div class="text-h6">
                                <v-icon icon="mdi-arrow-up" color="warning" size="small" />
                                {{ formatRate(metrics.netRate.out) }}
                            </div>
                        </v-card-item>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Graphiques temps réel -->
            <v-row dense class="mt-2">
                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-title class="text-subtitle-1">CPU %</v-card-title>
                        <v-card-text>
                            <ApexChart
                                type="line" height="220"
                                :options="cpuChart.options" :series="cpuChart.series"
                            />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-title class="text-subtitle-1">RAM %</v-card-title>
                        <v-card-text>
                            <ApexChart
                                type="line" height="220"
                                :options="memoryChart.options" :series="memoryChart.series"
                            />
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-title class="text-subtitle-1">Débit réseau</v-card-title>
                        <v-card-text>
                            <ApexChart
                                type="area" height="220"
                                :options="networkChart.options" :series="networkChart.series"
                            />
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Disques -->
                <v-col cols="12" md="6">
                    <v-card>
                        <v-card-title class="text-subtitle-1">Disques / Partitions</v-card-title>
                        <v-card-text>
                            <div v-for="disk in snap.disks" :key="disk.mount" class="mb-4">
                                <div class="d-flex justify-space-between text-body-2 mb-1">
                                    <span>
                                        <v-icon icon="mdi-harddisk" size="small" class="mr-1" />
                                        {{ disk.mount }}
                                        <span class="text-medium-emphasis">({{ disk.filesystem }})</span>
                                    </span>
                                    <span class="text-medium-emphasis">
                                        {{ formatBytes(disk.used) }} / {{ formatBytes(disk.total) }}
                                        — libre {{ formatBytes(disk.available) }}
                                    </span>
                                </div>
                                <v-progress-linear
                                    :model-value="disk.usage_percent"
                                    :color="usageColor(disk.usage_percent)"
                                    height="8" rounded
                                />
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </template>

        <v-skeleton-loader v-else type="card@3" class="mt-4" />
    </div>
</template>
