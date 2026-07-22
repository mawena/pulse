<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const currentPassword = ref('');
const newPassword = ref('');
const confirmation = ref('');
const showPasswords = ref(false);
const errors = ref([]);

async function handleSubmit() {
    errors.value = [];

    if (newPassword.value !== confirmation.value) {
        errors.value = ['La confirmation ne correspond pas au nouveau mot de passe.'];
        return;
    }
    if (newPassword.value.length < 8) {
        errors.value = ['Le nouveau mot de passe doit contenir au moins 8 caractères.'];
        return;
    }

    try {
        await auth.updatePassword(currentPassword.value, newPassword.value, confirmation.value);
        router.push({ name: 'dashboard' });
    } catch (e) {
        const data = e.response?.data ?? {};
        errors.value = Object.values(data.errors ?? data.data ?? {})
            .flat()
            .filter((m) => typeof m === 'string');
        if (!errors.value.length) errors.value = ['Échec du changement de mot de passe.'];
    }
}

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="4">
                <v-card class="pa-4">
                    <v-card-title class="text-center">
                        <v-icon icon="mdi-lock-reset" color="warning" size="40" class="mb-2 d-block mx-auto" />
                        Changement de mot de passe requis
                    </v-card-title>
                    <v-card-subtitle class="text-center mb-4 text-wrap">
                        Pour des raisons de sécurité, vous devez définir un nouveau
                        mot de passe avant d'accéder à MawenaPulse.
                    </v-card-subtitle>
                    <v-card-text>
                        <v-form @submit.prevent="handleSubmit">
                            <v-text-field
                                v-model="currentPassword"
                                label="Mot de passe actuel"
                                :type="showPasswords ? 'text' : 'password'"
                                prepend-inner-icon="mdi-lock-outline"
                                autocomplete="current-password"
                                required
                            />
                            <v-text-field
                                v-model="newPassword"
                                label="Nouveau mot de passe"
                                :type="showPasswords ? 'text' : 'password'"
                                prepend-inner-icon="mdi-lock-plus-outline"
                                hint="8 caractères minimum"
                                autocomplete="new-password"
                                required
                            />
                            <v-text-field
                                v-model="confirmation"
                                label="Confirmer le nouveau mot de passe"
                                :type="showPasswords ? 'text' : 'password'"
                                prepend-inner-icon="mdi-lock-check-outline"
                                :append-inner-icon="showPasswords ? 'mdi-eye-off' : 'mdi-eye'"
                                autocomplete="new-password"
                                required
                                @click:append-inner="showPasswords = !showPasswords"
                            />
                            <v-alert v-if="errors.length" type="error" density="compact" class="mb-4">
                                <div v-for="(message, i) in errors" :key="i">{{ message }}</div>
                            </v-alert>
                            <v-btn
                                type="submit"
                                color="primary"
                                block
                                size="large"
                                :loading="auth.loading"
                            >
                                Changer le mot de passe
                            </v-btn>
                            <v-btn variant="text" block class="mt-2" @click="handleLogout">
                                Se déconnecter
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
