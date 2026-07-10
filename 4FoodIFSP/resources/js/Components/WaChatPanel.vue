<script setup>
import axios from 'axios';
import { nextTick, ref, watch } from 'vue';
import { formatPhoneE164 } from '../utils/formatPhoneE164.js';
import OrderInformModal from './OrderInformModal.vue';
import ContactOrdersModal from './ContactOrdersModal.vue';

const props = defineProps({
    ticket:    { type: Object,  required: true },
    messages:  { type: Array,   default: () => [] },
    loading:   { type: Boolean, default: false },
    accepting: { type: Boolean, default: false },
});

const emit = defineEmits(['message-sent', 'close', 'reopen', 'return-triage', 'mark-unread', 'accept']);

const menuOpen = ref(false);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
}

// Menu de anexos "+" (apenas visual, sem função por enquanto).
const attachOpen = ref(false);

const attachOptions = [
    { key: 'pedido',    label: 'Informar pedido',          color: '#e08e2a', icon: 'receipt' },
    { key: 'local',     label: 'Enviar localização',       color: '#d23f3f', icon: 'location' },
    { key: 'pagamento', label: 'Link de pagamento',        color: '#22a559', icon: 'payment' },
];

function toggleAttach() {
    attachOpen.value = !attachOpen.value;
}

const sendingLocation = ref(false);

const orderModalOpen = ref(false);
const contactOrdersOpen = ref(false);

function onAttachSelect(key) {
    attachOpen.value = false;
    if (key === 'local') {
        sendLocation();
    } else if (key === 'pedido') {
        orderModalOpen.value = true;
    } else if (key === 'pagamento') {
        sendPayment();
    }
    // Demais opções ainda são visuais (sem ação).
}

const sendingPayment = ref(false);

async function sendPayment() {
    if (props.ticket.status !== 'in_progress' || sendingPayment.value) return;

    sendingPayment.value = true;
    sendError.value = null;

    try {
        const { data } = await axios.post(`/whatsapp/inbox/tickets/${props.ticket.id}/send-payment`);
        emit('message-sent', data.message);
    } catch (e) {
        sendError.value = e.response?.data?.message ?? 'Erro ao enviar o link de pagamento.';
    } finally {
        sendingPayment.value = false;
    }
}

function sendLocation() {
    if (props.ticket.status !== 'in_progress' || sendingLocation.value) return;

    if (!('geolocation' in navigator)) {
        sendError.value = 'Geolocalização não é suportada neste navegador.';
        return;
    }

    sendingLocation.value = true;
    sendError.value = null;

    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            try {
                const { latitude, longitude } = pos.coords;
                const { data } = await axios.post(
                    `/whatsapp/inbox/tickets/${props.ticket.id}/send-location`,
                    { latitude, longitude },
                );
                emit('message-sent', data.message);
            } catch (e) {
                sendError.value = e.response?.data?.message ?? 'Erro ao enviar localização.';
            } finally {
                sendingLocation.value = false;
            }
        },
        (err) => {
            sendingLocation.value = false;
            sendError.value = err.code === err.PERMISSION_DENIED
                ? 'Permissão de localização negada.'
                : 'Não foi possível obter a localização.';
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
}

// OpenStreetMap: mapa embutido + link para abrir em tela cheia.
function osmEmbed(lat, lng) {
    const d = 0.0025;
    const bbox = `${lng - d}%2C${lat - d}%2C${lng + d}%2C${lat + d}`;
    return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat}%2C${lng}`;
}

function osmLink(lat, lng) {
    return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`;
}

function menuAction(action) {
    menuOpen.value = false;
    if (action === 'triage') emit('return-triage');
    if (action === 'unread')  emit('mark-unread');
}

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
watch(() => props.ticket, () => { menuOpen.value = false; attachOpen.value = false; orderModalOpen.value = false; contactOrdersOpen.value = false; });
</script>

<template>
    <div class="chat-panel" @click="menuOpen = false; attachOpen = false">
        <header class="chat-head">
            <button
                type="button"
                class="chat-avatar chat-avatar--btn"
                title="Ver pedidos do contato"
                @click="contactOrdersOpen = true"
            >
                {{ initials(ticket.customer_name) }}
            </button>
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

                <!-- menu 3 pontos verticais -->
                <div v-if="ticket.status !== 'triage'" class="chat-menu-wrap">
                    <button
                        type="button"
                        class="chat-menu-btn"
                        aria-label="Mais opções"
                        @click.stop="toggleMenu"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="5"  r="1.5"/>
                            <circle cx="12" cy="12" r="1.5"/>
                            <circle cx="12" cy="19" r="1.5"/>
                        </svg>
                    </button>
                    <div v-if="menuOpen" class="chat-menu-dropdown" @click.stop>
                        <button
                            v-if="ticket.status === 'in_progress'"
                            type="button"
                            class="chat-menu-item"
                            @click="menuAction('triage')"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2Z"/>
                            </svg>
                            Retornar à triagem
                        </button>
                        <button type="button" class="chat-menu-item" @click="menuAction('unread')">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/>
                            </svg>
                            Marcar como não lido
                        </button>
                    </div>
                </div>
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
                <div class="chat-bubble" :class="[msg.direction, { 'chat-bubble--location': msg.type === 'location' }]">
                    <div v-if="msg.type === 'location'" class="chat-location">
                        <iframe
                            class="chat-location-map"
                            :src="osmEmbed(msg.latitude, msg.longitude)"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Localização"
                        />
                        <a
                            class="chat-location-link"
                            :href="osmLink(msg.latitude, msg.longitude)"
                            target="_blank"
                            rel="noopener"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" />
                            </svg>
                            Abrir no mapa
                        </a>
                    </div>
                    <p v-else class="chat-bubble-text">{{ msg.body }}</p>
                    <span class="chat-bubble-time">{{ formatTime(msg.sent_at) }}</span>
                </div>
            </div>
        </div>

        <div v-if="ticket.status === 'triage'" class="chat-compose chat-compose--triage">
            <button
                type="button"
                class="chat-atender-btn"
                :disabled="accepting"
                @click="emit('accept')"
            >
                {{ accepting ? 'Atendendo…' : 'Atender' }}
            </button>
        </div>

        <div v-else-if="ticket.status === 'in_progress'" class="chat-compose">
            <p v-if="sendError" class="compose-error">{{ sendError }}</p>
            <div class="compose-row">
                <div class="compose-attach-wrap">
                    <button
                        type="button"
                        class="compose-attach"
                        :class="{ active: attachOpen }"
                        aria-label="Anexar"
                        @click.stop="toggleAttach"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                        </svg>
                    </button>

                    <transition name="attach-pop">
                        <div v-if="attachOpen" class="attach-menu" @click.stop>
                            <button
                                v-for="opt in attachOptions"
                                :key="opt.key"
                                type="button"
                                class="attach-item"
                                @click="onAttachSelect(opt.key)"
                            >
                                <span class="attach-icon" :style="{ background: opt.color }">
                                    <svg v-if="opt.icon === 'menu'" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M8.1 13.34l2.83-2.83L3.91 3.5a4 4 0 000 5.66l4.19 4.18zm6.78-1.81c1.53.71 3.68.21 5.27-1.38 1.91-1.91 2.28-4.65.81-6.12-1.46-1.46-4.2-1.1-6.12.81-1.59 1.59-2.09 3.74-1.38 5.27L3.7 19.87l1.41 1.41L12 14.41l6.88 6.88 1.41-1.41L13.41 13l1.47-1.47z" />
                                    </svg>
                                    <svg v-else-if="opt.icon === 'clock'" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z" />
                                    </svg>
                                    <svg v-else-if="opt.icon === 'receipt'" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 17H6v-2h12v2zm0-4H6v-2h12v2zm0-4H6V7h12v2zM3 22l1.5-1.5L6 22l1.5-1.5L9 22l1.5-1.5L12 22l1.5-1.5L15 22l1.5-1.5L18 22l1.5-1.5L21 22V2l-1.5 1.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2 4.5 3.5 3 2v20z" />
                                    </svg>
                                    <svg v-else-if="opt.icon === 'location'" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" />
                                    </svg>
                                    <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z" />
                                    </svg>
                                </span>
                                <span class="attach-label">{{ opt.label }}</span>
                            </button>
                        </div>
                    </transition>
                </div>

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

        <OrderInformModal
            v-if="orderModalOpen"
            :ticket="ticket"
            @close="orderModalOpen = false"
            @saved="orderModalOpen = false"
        />

        <ContactOrdersModal
            v-if="contactOrdersOpen"
            :ticket="ticket"
            @close="contactOrdersOpen = false"
        />
    </div>
</template>

<style scoped src="./styles/WaChatPanel.css"></style>
