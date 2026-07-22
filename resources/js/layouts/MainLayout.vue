<script setup>
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMetricsStore } from '@/stores/metrics';
import PulseLogo from '@/components/PulseLogo.vue';

const auth = useAuthStore();
const metrics = useMetricsStore();
const router = useRouter();
const { mdAndUp } = useDisplay();

// Desktop : sidebar compactable en rail. Mobile : bottom navigation.
const rail = ref(false);

const navItems = computed(() =>
    [
        { title: 'Dashboard', short: 'Dash', icon: 'mdi-view-dashboard-outline', to: { name: 'dashboard' }, permission: ['read', 'system'] },
        { title: 'Processus', short: 'Process', icon: 'mdi-memory', to: { name: 'processes' }, permission: ['read', 'process'] },
        { title: 'Services LNMP', short: 'Services', icon: 'mdi-server-network', to: { name: 'services' }, permission: ['read', 'service'] },
        { title: 'Utilisateurs', short: 'Users', icon: 'mdi-account-group-outline', to: { name: 'users' }, permission: ['read', 'user'] },
        { title: 'Audit Trail', short: 'Audit', icon: 'mdi-clipboard-text-clock-outline', to: { name: 'audit-logs' }, permission: ['read', 'audit-log'] },
    ].filter((item) => auth.can(...item.permission)),
);

const initials = computed(() =>
    (auth.user?.name ?? '?')
        .split(/\s+/)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const roleLabel = computed(() => auth.user?.roles?.[0]?.label ?? (auth.can('manage', 'system') ? 'Admin' : 'Observateur'));

const lastUpdate = computed(() =>
    metrics.snapshot ? new Date(metrics.snapshot.timestamp).toLocaleTimeString('fr-FR') : null,
);

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <!-- Sidebar desktop uniquement — sur mobile la bottom-nav prend le relais -->
    <v-navigation-drawer
        v-if="mdAndUp"
        :rail="rail"
        permanent
        width="232"
    >
        <div class="d-flex align-center pa-3" style="min-height: 64px">
            <PulseLogo :size="32" :with-text="!rail" />
        </div>
        <v-divider />
        <v-list density="comfortable" nav>
            <v-list-item
                v-for="item in navItems"
                :key="item.title"
                :prepend-icon="item.icon"
                :title="item.title"
                :to="item.to"
                rounded="lg"
                color="primary"
            />
        </v-list>
        <template #append>
            <v-divider />
            <v-list density="compact" nav>
                <v-list-item
                    :prepend-icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
                    :title="rail ? '' : 'Réduire'"
                    rounded="lg"
                    @click="rail = !rail"
                />
            </v-list>
        </template>
    </v-navigation-drawer>

    <v-app-bar flat height="64">
        <div v-if="!mdAndUp" class="ml-3">
            <PulseLogo :size="30" with-text />
        </div>

        <v-spacer />

        <!-- Indicateur LIVE : battement au rythme du polling -->
        <div
            v-if="lastUpdate"
            class="d-none d-sm-flex align-center ga-2 mr-3"
            :title="`Dernière mesure à ${lastUpdate}`"
        >
            <span class="live-dot" />
            <span class="text-caption text-medium-emphasis font-data">{{ lastUpdate }}</span>
        </div>

        <!-- Menu utilisateur -->
        <v-menu location="bottom end" min-width="230">
            <template #activator="{ props }">
                <v-btn v-bind="props" variant="text" class="mr-2" height="44">
                    <v-avatar color="surface-variant" size="32" class="mr-2">
                        <span class="text-caption font-weight-bold">{{ initials }}</span>
                    </v-avatar>
                    <span class="d-none d-sm-inline">{{ auth.user?.name }}</span>
                    <v-icon icon="mdi-chevron-down" size="small" class="ml-1" />
                </v-btn>
            </template>
            <v-list density="comfortable">
                <v-list-item :title="auth.user?.name" :subtitle="auth.user?.email">
                    <template #prepend>
                        <v-avatar color="primary" size="36">
                            <span class="text-caption font-weight-bold" style="color:#06251F">{{ initials }}</span>
                        </v-avatar>
                    </template>
                </v-list-item>
                <v-list-item>
                    <v-chip size="small" color="primary" variant="tonal" prepend-icon="mdi-shield-account-outline">
                        {{ roleLabel }}
                    </v-chip>
                </v-list-item>
                <v-divider class="my-1" />
                <v-list-item
                    prepend-icon="mdi-lock-reset"
                    title="Changer le mot de passe"
                    :to="{ name: 'change-password' }"
                />
                <v-list-item
                    prepend-icon="mdi-logout"
                    title="Se déconnecter"
                    @click="handleLogout"
                />
            </v-list>
        </v-menu>
    </v-app-bar>

    <v-main>
        <v-container fluid class="pulse-main-content pa-4 pa-md-6" style="max-width: 1400px">
            <router-view />
        </v-container>
    </v-main>

    <!-- Navigation mobile : accessible au pouce -->
    <v-bottom-navigation
        v-if="!mdAndUp"
        grow
        :elevation="0"
        bg-color="surface"
        color="primary"
        height="64"
    >
        <v-btn v-for="item in navItems" :key="item.title" :to="item.to" size="small">
            <v-icon :icon="item.icon" size="22" />
            <span class="text-caption">{{ item.short }}</span>
        </v-btn>
    </v-bottom-navigation>
</template>
