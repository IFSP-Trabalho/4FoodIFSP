<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatPhoneE164 } from '../utils/formatPhoneE164.js';

const props = defineProps({
    connection: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['disconnect', 'request-qr', 'cancel-pairing', 'delete']);

const isKebabOpen = ref(false);

const isConnected    = computed(() => props.connection.connection_status === 'connected');
const isDisconnected = computed(() => props.connection.connection_status === 'disconnected');
const isPairing      = computed(() => props.connection.connection_status === 'pairing');

const statusLabel = computed(() => {
    if (isConnected.value)    return 'CONECTADO';
    if (isPairing.value)      return 'PAREAMENTO';
    return 'DESCONECTADO';
});

const statusDesc = computed(() => {
    if (isConnected.value) return 'Conexão estabelecida';
    if (isPairing.value)   return 'Escaneie o QR Code no celular';
    return 'Aguardando pareamento';
});

const formattedPhone = computed(() => formatPhoneE164(props.connection.phone_number));

const formattedTimestamp = computed(() => {
    const raw = props.connection.last_status_at ?? props.connection.updated_at;
    if (!raw) return null;
    const date = new Date(raw);
    const day     = String(date.getDate()).padStart(2, '0');
    const month   = String(date.getMonth() + 1).padStart(2, '0');
    const year    = date.getFullYear();
    const hours   = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
});

function toggleKebab(event) {
    event.stopPropagation();
    isKebabOpen.value = !isKebabOpen.value;
}

function onDelete(event) {
    event.stopPropagation();
    isKebabOpen.value = false;
    emit('delete', props.connection.id);
}

function onDocumentClick() {
    isKebabOpen.value = false;
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <article class="wa-card">
        <header class="wa-card-header">
            <div class="wa-card-brand">
                <div class="wa-card-logo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="white">
                        <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.37 5.07L2 22l5.09-1.34A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.2 14.2c-.22.62-1.3 1.18-1.8 1.22-.46.04-.9.2-3.05-.64-2.6-1.03-4.26-3.67-4.39-3.84-.13-.17-1.07-1.42-1.07-2.71 0-1.29.67-1.93.91-2.19.24-.26.52-.33.7-.33l.5.01c.16 0 .38-.06.6.46l.77 1.87c.06.15.1.32.01.5l-.29.52-.44.45c-.13.13-.27.27-.12.53.15.26.68 1.12 1.46 1.81.99.88 1.84 1.16 2.1 1.29.26.13.41.11.56-.07l.72-.85c.19-.22.37-.14.62-.05l1.77.83c.26.12.43.18.49.28.07.1.07.56-.15 1.11Z" />
                    </svg>
                </div>
                <div class="wa-card-info">
                    <span class="wa-card-name">{{ connection.name }}</span>
                    <span v-if="formattedPhone" class="wa-card-phone">{{ formattedPhone }}</span>
                </div>
            </div>

            <div class="wa-card-kebab-wrapper">
                <button
                    type="button"
                    class="wa-card-kebab"
                    aria-label="Mais ações"
                    @click="toggleKebab"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="5" r="1.5" />
                        <circle cx="12" cy="12" r="1.5" />
                        <circle cx="12" cy="19" r="1.5" />
                    </svg>
                </button>
                <div v-if="isKebabOpen" class="wa-card-kebab-menu">
                    <button type="button" class="wa-card-kebab-item danger" @click="onDelete">
                        Excluir
                    </button>
                </div>
            </div>
        </header>

        <div class="wa-card-body">
            <div class="wa-card-status-row">
                <div class="wa-card-status-icon" :class="connection.connection_status" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8 3 3 3-3a4.237 4.237 0 0 0-6 0zm-4-4 2 2a7.074 7.074 0 0 1 10 0l2-2C15.14 9.14 8.87 9.14 5 13z" />
                    </svg>
                </div>
                <div class="wa-card-status-text">
                    <strong class="wa-card-status-title" :class="connection.connection_status">
                        {{ statusLabel }}
                    </strong>
                    <span class="wa-card-status-desc">{{ statusDesc }}</span>
                    <span v-if="formattedTimestamp" class="wa-card-timestamp">
                        <span class="wa-card-timestamp-label">Última atualização:</span>
                        {{ formattedTimestamp }}
                    </span>
                </div>
            </div>
        </div>

        <footer class="wa-card-footer">
            <button
                v-if="isConnected"
                type="button"
                class="wa-card-action disconnect"
                @click="emit('disconnect', connection.id)"
            >
                Desconectar
            </button>
            <button
                v-else-if="isPairing"
                type="button"
                class="wa-card-action cancel-pairing"
                @click="emit('cancel-pairing', connection.id)"
            >
                Cancelar pareamento
            </button>
            <button
                v-else
                type="button"
                class="wa-card-action request-qr"
                @click="emit('request-qr', connection.id)"
            >
                Solicitar QR Code
            </button>
        </footer>
    </article>
</template>

<style scoped src="./styles/WaConnectionCard.css"></style>
