<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import PulseLogo from '@/components/PulseLogo.vue';

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
                ? 'Email ou mot de passe incorrect.'
                : 'Le serveur ne répond pas. Réessayez dans un instant.';
    }
}
</script>

<template>
    <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="5" lg="4" xl="3">
                <div class="d-flex flex-column align-center mb-6">
                    <PulseLogo :size="56" />
                    <h1 class="text-h5 pulse-display font-weight-bold mt-4">
                        Mawena<span style="color:#35E0C2">Pulse</span>
                    </h1>
                    <p class="text-body-2 text-medium-emphasis mt-1">
                        Les signes vitaux de votre serveur, en direct.
                    </p>
                </div>

                <v-card class="pa-5">
                    <v-form @submit.prevent="handleSubmit">
                        <v-text-field
                            v-model="email"
                            label="Email"
                            type="email"
                            prepend-inner-icon="mdi-email-outline"
                            autocomplete="username"
                            autofocus
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
                        <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mb-4">
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
                </v-card>

                <p class="text-caption text-medium-emphasis text-center mt-4 font-data">
                    pulse.mawena.cloud
                </p>
            </v-col>
        </v-row>
    </v-container>
</template>
