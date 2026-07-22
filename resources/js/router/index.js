import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/change-password',
        name: 'change-password',
        component: () => import('@/pages/ChangePasswordView.vue'),
        meta: { auth: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/MainLayout.vue'),
        meta: { auth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/DashboardView.vue'),
                meta: { permission: ['read', 'system'] },
            },
            {
                path: 'processes',
                name: 'processes',
                component: () => import('@/pages/ProcessManagerView.vue'),
                meta: { permission: ['read', 'process'] },
            },
            {
                path: 'services',
                name: 'services',
                component: () => import('@/pages/LnmpServicesView.vue'),
                meta: { permission: ['read', 'service'] },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('@/pages/UsersView.vue'),
                meta: { permission: ['read', 'user'] },
            },
            {
                path: 'audit-logs',
                name: 'audit-logs',
                component: () => import('@/pages/AuditLogsView.vue'),
                meta: { permission: ['read', 'audit-log'] },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
    // Recharge l'utilisateur (et ses ability_rules) après un refresh de page.
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchUser();
        } catch {
            // Token invalide : l'intercepteur HTTP gère la redirection.
            return;
        }
    }

    // Changement de mot de passe obligatoire : le backend (account.status)
    // bloque tout sauf update-password et logout — on force la page dédiée.
    if (auth.user?.password_change_required && to.name !== 'change-password') {
        return { name: 'change-password' };
    }
    if (!auth.user?.password_change_required && to.name === 'change-password') {
        return { name: 'dashboard' };
    }

    // Contrôle de permission côté client (le backend reste l'autorité).
    if (to.meta.permission && auth.user && to.name !== 'dashboard') {
        const [action, subject] = to.meta.permission;
        if (!auth.can(action, subject)) {
            return { name: 'dashboard' };
        }
    }
});

export default router;
