<script setup>
// Signature visuelle de MawenaPulse : tracé ECG qui se dessine en boucle.
// L'animation est purement CSS (.ecg-path) et respecte prefers-reduced-motion.
defineProps({
    size: { type: Number, default: 36 },
    withText: { type: Boolean, default: false },
});
</script>

<template>
    <div class="d-flex align-center ga-2">
        <svg
            :width="size"
            :height="size"
            viewBox="0 0 48 48"
            fill="none"
            role="img"
            aria-label="MawenaPulse"
        >
            <rect x="1.5" y="1.5" width="45" height="45" rx="10" stroke="#232F4B" stroke-width="3" />
            <path
                class="ecg-path"
                d="M6 26 H16 L20 14 L26 36 L30 22 L33 26 H42"
                stroke="#35E0C2"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <div v-if="withText" class="pulse-display">
            <span class="text-h6 font-weight-bold">Mawena<span style="color:#35E0C2">Pulse</span></span>
        </div>
    </div>
</template>
