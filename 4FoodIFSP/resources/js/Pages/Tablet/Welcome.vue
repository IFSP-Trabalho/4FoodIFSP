<script setup>
import './styles/Welcome.css';
import { ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    mesa:       { type: Number, required: true },
    mesa_label: { type: String, default: '' },
});

const loading = ref(false);
const error   = ref('');

async function iniciarPedido() {
    if (loading.value) return;
    loading.value = true;
    error.value   = '';

    try {
        await axios.post('/tablet/session/start', { mesa: props.mesa });
        router.visit('/tablet?mesa=' + props.mesa);
    } catch (e) {
        const status = e.response?.status;
        if (status === 422) {
            router.visit('/tablet?mesa=' + props.mesa);
        } else {
            error.value = 'Não foi possível iniciar o pedido. Tente novamente.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="welcome-screen theme-force-light">
        <div class="welcome-card">
            <h1 class="welcome-table-number">{{ mesa_label || 'Mesa ' + mesa }}</h1>
            <p class="welcome-subtitle">Bem-vindo! Toque para começar seu pedido.</p>
            <button
                type="button"
                class="welcome-btn"
                :disabled="loading"
                @click="iniciarPedido"
            >
                {{ loading ? 'Aguarde...' : 'INICIAR PEDIDO' }}
            </button>
            <p v-if="error" class="welcome-error">{{ error }}</p>
        </div>
    </div>
</template>
