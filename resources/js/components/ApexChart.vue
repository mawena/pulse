<script setup>
// Wrapper minimal autour d'apexcharts — remplace vue3-apexcharts qui
// embarquait sa propre copie vendored de la librairie (~500 kB dupliqués
// dans le bundle). Import dynamique : apexcharts n'est chargé qu'à
// l'affichage d'un graphique.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    type: { type: String, default: 'line' },
    height: { type: [Number, String], default: 'auto' },
    options: { type: Object, default: () => ({}) },
    series: { type: Array, default: () => [] },
});

const el = ref(null);
let chart = null;

function buildConfig() {
    return {
        ...props.options,
        chart: { ...(props.options.chart ?? {}), type: props.type, height: props.height },
        series: props.series,
    };
}

onMounted(async () => {
    const { default: ApexCharts } = await import('apexcharts');
    chart = new ApexCharts(el.value, buildConfig());
    await chart.render();
});

// Mise à jour des données sans recréer le graphique (polling temps réel).
watch(
    () => [props.series, props.options],
    () => chart?.updateOptions(buildConfig(), false, true),
    { deep: true },
);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div ref="el" />
</template>
