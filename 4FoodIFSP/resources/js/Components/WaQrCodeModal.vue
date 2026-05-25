<script setup>
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    connection: { type: Object, required: true },
    qrBase64:   { type: String,  default: null },
    expiresAt:  { type: String,  default: null },
    loading:    { type: Boolean, default: false },
    error:      { type: String,  default: null },
});

const emit = defineEmits(['close', 'paired', 'cancel-pairing']);

const POLL_MS = 3000;

const currentQr       = ref(props.qrBase64);
const currentExpires  = ref(props.expiresAt);
const isGenerating    = ref(false);
const isCancelling    = ref(false);
const pollError       = ref(null);

let pollTimer    = null;
let countdownTimer = null;
const secondsLeft = ref(0);

watch(() => props.qrBase64, (v) => { currentQr.value = v; });
watch(() => props.expiresAt, (v) => {
    currentExpires.value = v;
    resetCountdown();
});
watch(() => props.loading, (newVal, oldVal) => {
    if (oldVal && !newVal && !props.error && !pollTimer) {
        startPolling();
    }
});

const isExpired = computed(() => secondsLeft.value <= 0 && currentExpires.value !== null);
const countdownText = computed(() => {
    if (!currentExpires.value) return '';
    if (secondsLeft.value <= 0) return 'QR expirado';
    const m = Math.floor(secondsLeft.value / 60);
    const s = secondsLeft.value % 60;
    return m > 0 ? `${m}:${String(s).padStart(2, '0')}` : `0:${String(s).padStart(2, '0')}`;
});

function resetCountdown() {
    clearInterval(countdownTimer);
    if (!currentExpires.value) return;

    function tick() {
        const diff = Math.max(0, Math.floor((new Date(currentExpires.value) - Date.now()) / 1000));
        secondsLeft.value = diff;
    }
    tick();
    countdownTimer = setInterval(tick, 1000);
}

function startPolling() {
    pollTimer = setInterval(async () => {
        if (document.visibilityState === 'hidden') return;
        try {
            const { data } = await axios.get(
                `/admin/whatsapp/conexoes/${props.connection.id}/status`
            );

            if (data.connection_status === 'connected') {
                clearInterval(pollTimer);
                emit('paired');
                return;
            }

            if (data.qr_base64 && data.qr_base64 !== currentQr.value) {
                currentQr.value      = data.qr_base64;
                currentExpires.value = data.expires_at ?? null;
                resetCountdown();
            }
        } catch {
            // Silencia erros de poll — não interrompe o fluxo
        }
    }, POLL_MS);
}

async function handleGenerateQr() {
    isGenerating.value = true;
    pollError.value = null;

    try {
        const { data } = await axios.post(
            `/admin/whatsapp/conexoes/${props.connection.id}/request-qr`
        );

        if (data.connection_status === 'connected') {
            emit('paired');
            return;
        }

        currentQr.value      = data.qr_base64 ?? null;
        currentExpires.value = data.expires_at ?? null;
        resetCountdown();
    } catch (e) {
        pollError.value = e.response?.data?.message ?? 'Erro ao gerar novo QR.';
    } finally {
        isGenerating.value = false;
    }
}

async function handleCancelPairing() {
    isCancelling.value = true;
    try {
        await axios.post(
            `/admin/whatsapp/conexoes/${props.connection.id}/cancel-pairing`
        );
    } catch {
        // Ignora erro — fecha modal mesmo assim
    } finally {
        isCancelling.value = false;
        emit('cancel-pairing');
    }
}

function handleClose() {
    handleCancelPairing();
}

function onKeydown(e) {
    if (e.key === 'Escape') handleClose();
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    resetCountdown();
    if (!props.loading && !props.error) startPolling();
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    clearInterval(pollTimer);
    clearInterval(countdownTimer);
});
</script>

<template>
    <div class="admin-modal-overlay" @click.self="handleClose"></div>
    <section
        class="admin-modal wa-qr-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="wa-qr-title"
    >
        <header class="admin-modal-head wa-qr-modal-head">
            <div>
                <h3 id="wa-qr-title">Parear WhatsApp</h3>
                <p>{{ connection.name }}</p>
            </div>
            <button
                type="button"
                class="wa-qr-close"
                aria-label="Fechar"
                @click="handleClose"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </header>

        <div class="wa-qr-body">
            <!-- Loading inicial -->
            <div v-if="loading" class="wa-qr-loading">
                <div class="wa-qr-spinner"></div>
                <p>Gerando QR Code…</p>
            </div>

            <!-- Erro 503 / erro geral -->
            <div v-else-if="error || pollError" class="wa-qr-error-state">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p>{{ error || pollError }}</p>
                <button
                    type="button"
                    class="wa-qr-btn-retry"
                    :disabled="isGenerating"
                    @click="handleGenerateQr"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- QR Code -->
            <template v-else>
                <div class="wa-qr-image-wrap">
                    <img
                        v-if="currentQr && !isExpired"
                        :src="currentQr"
                        alt="QR Code WhatsApp"
                        class="wa-qr-image"
                    />
                    <div v-else-if="isExpired" class="wa-qr-expired">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <p>QR expirado</p>
                    </div>
                    <div v-else class="wa-qr-placeholder">
                        <div class="wa-qr-spinner"></div>
                    </div>
                </div>

                <ol class="wa-qr-steps">
                    <li>Abra o WhatsApp no celular</li>
                    <li>Toque em Menu ⋮ → <strong>Aparelhos conectados</strong></li>
                    <li>Toque em <strong>Conectar aparelho</strong></li>
                    <li>Aponte a câmera para este QR Code</li>
                </ol>

                <p v-if="currentExpires" class="wa-qr-countdown" :class="{ expired: isExpired }">
                    {{ isExpired ? 'QR expirado' : `Expira em: ${countdownText}` }}
                </p>
            </template>
        </div>

        <footer class="admin-modal-actions">
            <button
                type="button"
                class="secondary"
                :disabled="isCancelling"
                @click="handleCancelPairing"
            >
                {{ isCancelling ? 'Cancelando…' : 'Cancelar pareamento' }}
            </button>
            <button
                v-if="!loading && !error"
                type="button"
                class="primary"
                :disabled="isGenerating || (!isExpired && !!currentQr)"
                @click="handleGenerateQr"
            >
                {{ isGenerating ? 'Gerando…' : 'Gerar novo QR' }}
            </button>
        </footer>
    </section>
</template>

<style scoped src="./styles/WaQrCodeModal.css"></style>
