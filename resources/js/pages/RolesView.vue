<script setup>
import { onMounted, ref } from 'vue';
import http from '@/lib/http';
import PageHeader from '@/components/PageHeader.vue';

const roles = ref([]);
const permissions = ref([]);
const loading = ref(false);
const snackbar = ref({ show: false, text: '', color: 'success' });

const editDialog = ref({ show: false, isNew: true, loading: false, form: {}, permissionIds: [] });
const deleteDialog = ref({ show: false, role: null, loading: false });

async function fetchAll() {
    loading.value = true;
    try {
        const [rolesRes, permsRes] = await Promise.all([
            http.get('/roles', { params: { paginate: 'false', relation: 'permissions' } }),
            http.get('/permissions', { params: { paginate: 'false' } }),
        ]);
        roles.value = rolesRes.data.data;
        permissions.value = permsRes.data.data;
    } finally {
        loading.value = false;
    }
}

function permissionLabel(permission) {
    return permission.label || `${permission.action} / ${permission.subject}`;
}

function openCreate() {
    editDialog.value = {
        show: true, isNew: true, loading: false,
        form: { name: '', label: '', description: '', is_super_admin: false },
        permissionIds: [],
    };
}

function openEdit(role) {
    editDialog.value = {
        show: true, isNew: false, loading: false,
        form: {
            id: role.id,
            name: role.name,
            label: role.label ?? '',
            description: role.description ?? '',
            is_super_admin: Boolean(role.is_super_admin),
        },
        permissionIds: (role.permissions ?? []).map((p) => p.id),
    };
}

async function saveRole() {
    editDialog.value.loading = true;
    const { form, isNew, permissionIds } = editDialog.value;
    try {
        const payload = { ...form, permissions: permissionIds };
        if (isNew) {
            await http.post('/roles', payload);
        } else {
            await http.put(`/roles/${form.id}`, payload);
        }
        snackbar.value = { show: true, text: 'Rôle enregistré.', color: 'success' };
        editDialog.value.show = false;
        await fetchAll();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? e.response?.data?.errors ?? {}).flat().join(' ') || 'Échec de l\'enregistrement.',
            color: 'error',
        };
    } finally {
        editDialog.value.loading = false;
    }
}

async function deleteRole() {
    deleteDialog.value.loading = true;
    try {
        await http.delete(`/roles/${deleteDialog.value.role.id}`);
        snackbar.value = { show: true, text: 'Rôle supprimé.', color: 'success' };
        deleteDialog.value.show = false;
        await fetchAll();
    } catch (e) {
        snackbar.value = {
            show: true,
            text: Object.values(e.response?.data?.data ?? e.response?.data?.errors ?? {}).flat().join(' ') || 'Échec de la suppression.',
            color: 'error',
        };
    } finally {
        deleteDialog.value.loading = false;
    }
}

onMounted(fetchAll);
</script>

<template>
    <div>
        <PageHeader
            title="Rôles & Permissions"
            :subtitle="`${roles.length} rôles · ${permissions.length} permissions (format CASL action/subject)`"
            icon="mdi-shield-account-outline"
        >
            <v-btn color="primary" prepend-icon="mdi-shield-plus-outline" @click="openCreate">
                Nouveau rôle
            </v-btn>
        </PageHeader>

        <v-row dense>
            <v-col v-for="role in roles" :key="role.id" cols="12" md="6" lg="4">
                <v-card class="h-100 d-flex flex-column">
                    <v-card-item>
                        <template #prepend>
                            <v-avatar
                                :color="role.is_super_admin ? 'error' : 'primary'"
                                variant="tonal" rounded="lg"
                            >
                                <v-icon :icon="role.is_super_admin ? 'mdi-shield-crown-outline' : 'mdi-shield-account-outline'" />
                            </v-avatar>
                        </template>
                        <v-card-title>{{ role.label ?? role.name }}</v-card-title>
                        <v-card-subtitle class="font-data">{{ role.name }}</v-card-subtitle>
                    </v-card-item>
                    <v-card-text class="flex-grow-1">
                        <p v-if="role.description" class="text-body-2 text-medium-emphasis mb-3">
                            {{ role.description }}
                        </p>
                        <v-chip
                            v-if="role.is_super_admin"
                            size="small" color="error" variant="tonal" prepend-icon="mdi-infinity"
                        >
                            Tous les droits (super-admin)
                        </v-chip>
                        <template v-else>
                            <v-chip
                                v-for="permission in role.permissions ?? []"
                                :key="permission.id"
                                size="x-small" class="mr-1 mb-1" variant="tonal" color="primary"
                            >
                                {{ permission.action }}/{{ permission.subject }}
                            </v-chip>
                            <span v-if="!(role.permissions ?? []).length" class="text-caption text-medium-emphasis">
                                Aucune permission — ce rôle ne donne accès à rien.
                            </span>
                        </template>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn prepend-icon="mdi-pencil" variant="text" size="small" @click="openEdit(role)">
                            Modifier
                        </v-btn>
                        <v-btn
                            prepend-icon="mdi-delete" variant="text" size="small" color="error"
                            :disabled="['admin', 'observer'].includes(role.name)"
                            @click="deleteDialog = { show: true, role, loading: false }"
                        >
                            Supprimer
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>

        <v-skeleton-loader v-if="loading && !roles.length" type="card@3" />

        <!-- Dialog création / édition -->
        <v-dialog v-model="editDialog.show" max-width="640" scrollable>
            <v-card>
                <v-card-title>{{ editDialog.isNew ? 'Nouveau rôle' : 'Modifier le rôle' }}</v-card-title>
                <v-card-text style="max-height: 65vh">
                    <v-form @submit.prevent="saveRole">
                        <v-text-field
                            v-model="editDialog.form.name"
                            label="Nom technique (unique)" hint="ex : support, devops" required
                        />
                        <v-text-field v-model="editDialog.form.label" label="Libellé affiché" />
                        <v-text-field v-model="editDialog.form.description" label="Description" />
                        <v-switch
                            v-model="editDialog.form.is_super_admin"
                            label="Super-admin (tous les droits, ignore la liste ci-dessous)"
                            color="error" hide-details class="mb-3"
                        />
                        <template v-if="!editDialog.form.is_super_admin">
                            <div class="text-subtitle-2 mb-2">Permissions accordées</div>
                            <v-checkbox
                                v-for="permission in permissions"
                                :key="permission.id"
                                v-model="editDialog.permissionIds"
                                :value="permission.id"
                                :label="`${permissionLabel(permission)} — ${permission.action}/${permission.subject}`"
                                density="compact" hide-details
                            />
                        </template>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="editDialog.show = false">Annuler</v-btn>
                    <v-btn color="primary" variant="flat" :loading="editDialog.loading" @click="saveRole">
                        Enregistrer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Dialog suppression -->
        <v-dialog v-model="deleteDialog.show" max-width="440">
            <v-card v-if="deleteDialog.role">
                <v-card-title class="text-error">Supprimer le rôle ?</v-card-title>
                <v-card-text>
                    Supprimer <strong>{{ deleteDialog.role.label ?? deleteDialog.role.name }}</strong> ?
                    Les utilisateurs qui l'ont perdront les permissions associées.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog.show = false">Annuler</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleteDialog.loading" @click="deleteRole">
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
