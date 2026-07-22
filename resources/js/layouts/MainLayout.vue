<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const drawer = ref(true);
const auth = useAuthStore();
const router = useRouter();

// Navigation filtrée selon les permissions CASL de l'utilisateur.
const navItems = computed(() =>
    [
        { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: { name: 'dashboard' }, permission: ['read', 'system'] },
        { title: 'Processus', icon: 'mdi-memory', to: { name: 'processes' }, permission: ['read', 'process'] },
        { title: 'Services LNMP', icon: 'mdi-server-network', to: { name: 'services' }, permission: ['read', 'service'] },
        { title: 'Utilisateurs', icon: 'mdi-account-group-outline', to: { name: 'users' }, permission: ['read', 'user'] },
        { title: 'Audit Trail', icon: 'mdi-clipboard-text-clock-outline', to: { name: 'audit-logs' }, permission: ['read', 'audit-log'] },
    ].filter((item) => auth.can(...item.permission)),
);

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <v-navigation-drawer v-model="drawer">
        <v-list-item
            prepend-icon="mdi-pulse"
            title="MawenaPulse"
            subtitle="Server monitoring"
            class="py-3"
        />
        <v-divider />
        <v-list density="compact" nav>
            <v-list-item
                v-for="item in navItems"
                :key="item.title"
                :prepend-icon="item.icon"
                :title="item.title"
                :to="item.to"
            />
        </v-list>
    </v-navigation-drawer>

    <v-app-bar flat>
        <v-app-bar-nav-icon @click="drawer = !drawer" />
        <v-spacer />
        <v-chip v-if="auth.user" prepend-icon="mdi-account-circle" variant="text" class="mr-2">
            {{ auth.user.name }}
        </v-chip>
        <v-btn icon="mdi-logout" title="Se déconnecter" @click="handleLogout" />
    </v-app-bar>

    <v-main>
        <v-container fluid>
            <router-view />
        </v-container>
    </v-main>
</template>
