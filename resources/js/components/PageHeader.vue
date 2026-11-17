<script setup>
// En-tête de page unifié : titre display + sous-titre + zone d'actions.
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: null },
});
</script>

<template>
    <div class="d-flex align-center flex-wrap ga-3 mb-5">
        <div class="d-flex align-center ga-3">
            <v-avatar v-if="icon" color="surface-variant" rounded="lg" size="42">
                <v-icon :icon="icon" color="primary" />
            </v-avatar>
            <div>
                <h1 class="text-h5 pulse-display font-weight-bold">{{ title }}</h1>
                <div v-if="subtitle" class="text-caption text-medium-emphasis">{{ subtitle }}</div>
            </div>
        </div>
        <v-spacer />
        <slot />
    </div>
</template>
