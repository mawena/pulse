<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const error = ref(null);

async function handleSubmit() {
    error.value = null;
    try {
        await auth.login(email.value, password.value);
        router.push(route.query.redirect ?? { name: 'dashboard' });
    } catch (e) {
        error.value =
            e.response?.status === 401
                ? 'Identifiants incorrects.'
                : 'Erreur de connexion au serveur.';
    }
}
</script>

<template>
    <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="4">
                <v-card class="pa-4">
                    <v-card-title class="text-center">
                        <v-icon icon="mdi-pulse" color="primary" size="40" class="mb-2 d-block mx-auto" />
                        MawenaPulse
                    </v-card-title>
                    <v-card-subtitle class="text-center mb-4">
                        Connexion au monitoring serveur
                    </v-card-subtitle>
                    <v-card-text>
                        <v-form @submit.prevent="handleSubmit">
                            <v-text-field
                                v-model="email"
                                label="Email"
                                type="email"
                                prepend-inner-icon="mdi-email-outline"
                                autocomplete="username"
                                required
                            />
                            <v-text-field
                                v-model="password"
                                label="Mot de passe"
                                :type="showPassword ? 'text' : 'password'"
                                prepend-inner-icon="mdi-lock-outline"
                                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                autocomplete="current-password"
                                required
                                @click:append-inner="showPassword = !showPassword"
                            />
                            <v-alert v-if="error" type="error" density="compact" class="mb-4">
                                {{ error }}
                            </v-alert>
                            <v-btn
                                type="submit"
                                color="primary"
                                block
                                size="large"
                                :loading="auth.loading"
                            >
                                Se connecter
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
