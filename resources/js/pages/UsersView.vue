<script setup>
import { onMounted, ref, watch } from 'vue';
import http from '@/lib/http';
import { useAuthStore } from '@/stores/auth';
import { formatDateTime } from '@/lib/format';

const auth = useAuthStore();

const users = ref([]);
const roles = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const page = ref(1);
const perPage = ref(10);
const snackbar = ref({ show: false, text: '', color: 'success' });

// Dialog création / édition
const editDialog = ref({ show: false, isNew: true, loading: false, form: {}, roleIds: [] });
const deleteDialog = ref({ show: false, user: null, loading: false });

const headers = [
    { title: 'Nom', key: 'name' },
    { title: 'Email', key: 'email' },
    { title: 'Rôles', key: 'roles', sortable: false },
    { title: 'Activé', key: 'activated' },
    { title: 'Créé le', key: 'created_at' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
];

async function fetchUsers() {
    loading.value = true;
    try {
        const { data } = await http.get('/users', {
            params: {
                search: search.value || undefined,
                page: page.value,
                per_page: perPage.value,
                relation: 'roles', // chargement de la relation via maravel
            },
        });
        users.value = data.data;
        total.value = data.total;
    } finally {
        loading.value = false;
    }
}

async function fetchRoles() {
    const { data } = await http.get('/roles', { params: { paginate: 'false' } });
    roles.value = data.data;
}

function openCreate() {
    editDialog.value = {
        show: true, isNew: true, loading: false,
        form: { name: '', email: '', password: '', activated: true, password_change_required: true },
        roleIds: [],
    };
}

function openEdit(user) {
    editDialog.value = {
        show: true, isNew: false, loading: false,
        form: {
            id: user.id,
            name: user.name,
            email: user.email,
            password: '',
            activated: Boolean(user.activated),
            password_change_required: Boolean(user.password_change_required),
        },
        roleIds: (user.roles ?? []).map((r) => r.id),
    };
}

async function saveUser() {
    editDialog.value.loading = true;
    const { form, isNew, roleIds } = editDialog.value;
    try {
        const payload = { ...form };
        if (!payload.password) delete payload.password;

        let userId = form.id;
        if (isNew) {
            const { data } = await http.post('/users', payload);
            userId = data.data?.User?.id ?? data.data?.user?.id;
        } else {
            await http.put(`/users/${form.id}`, payload);
        }
        if (userId) {
            await http.put(`/users/${userId}/roles`, { role_ids: roleIds });
        }

        snackbar.value = { show: true, text: 'Utilisateur enregistré.', color: 'success' };
        editDialog.value.show = false;
        await fetchUsers();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? {}).flat().join(' ') || 'Échec de l\'enregistrement.',
            color: 'error',
        };
    } finally {
        editDialog.value.loading = false;
    }
}

async function deleteUser() {
    deleteDialog.value.loading = true;
    try {
        await http.delete(`/users/${deleteDialog.value.user.id}`);
        snackbar.value = { show: true, text: 'Utilisateur supprimé.', color: 'success' };
        deleteDialog.value.show = false;
        await fetchUsers();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? {}).flat().join(' ') || 'Échec de la suppression.',
            color: 'error',
        };
    } finally {
        deleteDialog.value.loading = false;
    }
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        fetchUsers();
    }, 400);
});
watch([page, perPage], fetchUsers);

onMounted(() => {
    fetchUsers();
    fetchRoles();
});
</script>

<template>
    <div>
        <div class="d-flex align-center mb-4">
            <h1 class="text-h5">Utilisateurs & Rôles</h1>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-account-plus" @click="openCreate">
                Nouvel utilisateur
            </v-btn>
        </div>

        <v-card>
            <v-card-text>
                <v-text-field
                    v-model="search"
                    prepend-inner-icon="mdi-magnify"
                    label="Rechercher (nom, email)"
                    clearable hide-details class="mb-4"
                />
                <v-data-table-server
                    v-model:page="page"
                    v-model:items-per-page="perPage"
                    :headers="headers"
                    :items="users"
                    :items-length="total"
                    :loading="loading"
                    density="comfortable"
                    hover
                >
                    <template #item.roles="{ item }">
                        <v-chip
                            v-for="role in item.roles ?? []" :key="role.id"
                            size="x-small" class="mr-1"
                            :color="role.is_super_admin ? 'error' : 'primary'"
                        >
                            {{ role.label ?? role.name }}
                        </v-chip>
                    </template>
                    <template #item.activated="{ item }">
                        <v-icon
                            :icon="item.activated ? 'mdi-check-circle' : 'mdi-close-circle'"
                            :color="item.activated ? 'success' : 'error'" size="small"
                        />
                    </template>
                    <template #item.created_at="{ value }">
                        {{ formatDateTime(value) }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" />
                        <v-btn
                            icon="mdi-delete" size="small" variant="text" color="error"
                            :disabled="item.id === auth.user?.id"
                            @click="deleteDialog = { show: true, user: item, loading: false }"
                        />
                    </template>
                </v-data-table-server>
            </v-card-text>
        </v-card>

        <!-- Dialog création / édition -->
        <v-dialog v-model="editDialog.show" max-width="560">
            <v-card>
                <v-card-title>
                    {{ editDialog.isNew ? 'Nouvel utilisateur' : 'Modifier l\'utilisateur' }}
                </v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="saveUser">
                        <v-text-field v-model="editDialog.form.name" label="Nom" required />
                        <v-text-field v-model="editDialog.form.email" label="Email" type="email" required />
                        <v-text-field
                            v-model="editDialog.form.password"
                            :label="editDialog.isNew ? 'Mot de passe' : 'Mot de passe (laisser vide pour conserver)'"
                            type="password"
                        />
                        <v-select
                            v-model="editDialog.roleIds"
                            :items="roles"
                            item-title="label"
                            item-value="id"
                            label="Rôles"
                            multiple chips closable-chips
                        />
                        <v-switch
                            v-model="editDialog.form.activated"
                            label="Compte activé" color="success" hide-details
                        />
                        <v-switch
                            v-model="editDialog.form.password_change_required"
                            label="Changement de mot de passe requis" color="warning" hide-details
                        />
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="editDialog.show = false">Annuler</v-btn>
                    <v-btn color="primary" variant="flat" :loading="editDialog.loading" @click="saveUser">
                        Enregistrer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Dialog suppression -->
        <v-dialog v-model="deleteDialog.show" max-width="440">
            <v-card v-if="deleteDialog.user">
                <v-card-title class="text-error">Supprimer l'utilisateur ?</v-card-title>
                <v-card-text>
                    Supprimer définitivement <strong>{{ deleteDialog.user.name }}</strong>
                    ({{ deleteDialog.user.email }}) ?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog.show = false">Annuler</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleteDialog.loading" @click="deleteUser">
                        Supprimer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>
