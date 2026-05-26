<script setup>
import axios from 'axios';
import { nextTick, ref, watch } from 'vue';
import { formatPhoneE164 } from '../utils/formatPhoneE164.js';

const props = defineProps({
    ticket:   { type: Object,  required: true },
    messages: { type: Array,   default: () => [] },
    loading:  { type: Boolean, default: false },
});

const emit = defineEmits(['message-sent', 'close', 'reopen']);

const messagesEl = ref(null);
const inputText  = ref('');
const sending    = ref(false);
const sendError  = ref(null);

function initials(name) {
    if (!name?.trim()) return '?';
    const parts = name.trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : parts[0].slice(0, 2).toUpperCase();
}

function formatTime(sentAt) {
    if (!sentAt) return '';
    return new Date(sentAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

async function scrollToBottom() {
    await nextTick();
    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
}

async function submitSend() {
    const body = inputText.value.trim();
    if (!body || sending.value || props.ticket.status !== 'in_progress') return;

    sending.value   = true;
    sendError.value = null;

    try {
        const { data } = await axios.post(
            `/whatsapp/inbox/tickets/${props.ticket.id}/send`,
            { body },
        );
        inputText.value = '';
        emit('message-sent', data.message);
    } catch (e) {
        sendError.value = e.response?.data?.message ?? 'Erro ao enviar mensagem.';
    } finally {
        sending.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submitSend();
    }
}

watch(() => props.messages, scrollToBottom, { deep: true, immediate: true });
watch(() => props.loading, (v) => { if (!v) scrollToBottom(); });
</script>

<template>
    <div class="chat-panel">
        <header class="chat-head">
            <span class="chat-avatar">{{ initials(ticket.customer_name) }}</span>
            <div class="chat-contact">
                <strong>{{ ticket.customer_name || 'Cliente' }}</strong>
                <span>{{ formatPhoneE164(ticket.phone_number) || ticket.phone_number }}</span>
            </div>
            <div class="chat-head-actions">
                <span v-if="ticket.status === 'closed'" class="chat-closed-badge">Fechado</span>
                <button
                    v-if="ticket.status === 'closed'"
                    type="button"
                    class="chat-reopen-btn"
                    @click="emit('reopen')"
                >
                    Reabrir
                </button>
                <button
                    v-if="ticket.status === 'in_progress'"
                    type="button"
                    class="chat-close-btn"
                    aria-label="Fechar atendimento"
                    @click="emit('close')"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </header>

        <div v-if="loading" class="chat-loading">
            <div class="chat-spinner" />
            <p>Carregando mensagens…</p>
        </div>

        <div v-else ref="messagesEl" class="chat-messages">
            <div v-if="messages.length === 0" class="chat-empty">
                <p>Nenhuma mensagem ainda</p>
            </div>
            <div
                v-for="msg in messages"
                :key="msg.id"
                class="chat-bubble-wrap"
                :class="msg.direction"
            >
                <div class="chat-bubble" :class="msg.direction">
                    <p class="chat-bubble-text">{{ msg.body }}</p>
                    <span class="chat-bubble-time">{{ formatTime(msg.sent_at) }}</span>
                </div>
            </div>
        </div>

        <div v-if="ticket.status === 'in_progress'" class="chat-compose">
            <p v-if="sendError" class="compose-error">{{ sendError }}</p>
            <div class="compose-row">
                <textarea
                    v-model="inputText"
                    class="compose-input"
                    placeholder="Digite uma mensagem…"
                    rows="1"
                    :disabled="sending"
                    @keydown="onKeydown"
                />
                <button
                    type="button"
                    class="compose-send"
                    :disabled="!inputText.trim() || sending"
                    @click="submitSend"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/WaChatPanel.css"></style>
