<script setup>
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import WaChatPanel from '../../Components/WaChatPanel.vue';
import WaContactRow from '../../Components/WaContactRow.vue';

const props = defineProps({
    tickets:    { type: Object, required: true },
    date:       { type: String, required: true },
    authUserId: { type: String, default: null },
});

const activeTab   = ref('in_progress');
const search      = ref('');
const selectedId  = ref(null);
const chatMessages = ref([]);
const chatLoading  = ref(false);
const isAccepting  = ref(false);
const acceptError  = ref(null);

const POLL_MS = 5000;
let pollTimer           = null;
let lastSnapshot        = null;
let lastSelectedUpdated = null;

const tabDefs = [
    { key: 'in_progress', label: 'Em andamento' },
    { key: 'triage',      label: 'Triagem' },
    { key: 'closed',      label: 'Fechados' },
];

const allTickets = computed(() => [
    ...(props.tickets.triage      ?? []),
    ...(props.tickets.in_progress ?? []),
    ...(props.tickets.closed      ?? []),
]);

const selectedTicket = computed(() =>
    allTickets.value.find((t) => t.id === selectedId.value) ?? null
);

const currentList = computed(() => props.tickets[activeTab.value] ?? []);

const filteredList = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return currentList.value;
    return currentList.value.filter((t) => {
        const name  = (t.customer_name ?? '').toLowerCase();
        const phone = (t.phone_number  ?? '').toLowerCase();
        return name.includes(term) || phone.includes(term);
    });
});

function tabCount(key) {
    return (props.tickets[key] ?? []).length;
}

function onTabChange(key) {
    activeTab.value  = key;
    selectedId.value = null;
    chatMessages.value = [];
}

async function onSelect(id) {
    selectedId.value = id;
    acceptError.value = null;
    const ticket = allTickets.value.find((t) => t.id === id);
    if (ticket && (ticket.status === 'in_progress' || ticket.status === 'closed')) {
        await fetchMessages(id);
    } else {
        chatMessages.value = [];
    }
}

async function fetchMessages(ticketId) {
    chatLoading.value = true;
    try {
        const { data } = await axios.get(`/whatsapp/inbox/tickets/${ticketId}/messages`);
        chatMessages.value = data.messages ?? [];
    } catch {
        chatMessages.value = [];
    } finally {
        chatLoading.value = false;
    }
}

async function handleAccept(ticketId) {
    isAccepting.value = true;
    acceptError.value = null;
    try {
        await axios.patch(`/whatsapp/inbox/tickets/${ticketId}/accept`);
        activeTab.value  = 'in_progress';
        selectedId.value = ticketId;
        router.reload({ only: ['tickets'], preserveScroll: true });
        fetchMessages(ticketId);
    } catch (e) {
        acceptError.value = e.response?.data?.message ?? 'Erro ao atender ticket.';
    } finally {
        isAccepting.value = false;
    }
}

async function pollInbox() {
    if (document.visibilityState !== 'visible') return;
    try {
        const { data } = await axios.get('/whatsapp/inbox/poll');
        const snapshot = JSON.stringify(data);
        if (lastSnapshot !== null && snapshot !== lastSnapshot) {
            router.reload({ only: ['tickets'], preserveScroll: true });
        }
        lastSnapshot = snapshot;
    } catch {
        // silencia erros de poll
    }
}

// Detecta nova mensagem no ticket aberto sem refazer a lista toda
watch(selectedTicket, (ticket) => {
    if (!ticket) {
        lastSelectedUpdated = null;
        return;
    }
    const isChat = ticket.status === 'in_progress' || ticket.status === 'closed';
    if (
        isChat &&
        lastSelectedUpdated !== null &&
        ticket.updated_at !== lastSelectedUpdated
    ) {
        fetchMessages(ticket.id);
    }
    lastSelectedUpdated = ticket.updated_at ?? null;
}, { deep: true });

function formatTime(iso) {
    const d   = new Date(iso);
    const now = new Date();
    const sameDay = (a, b) =>
        a.getFullYear() === b.getFullYear() &&
        a.getMonth()    === b.getMonth() &&
        a.getDate()     === b.getDate();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (sameDay(d, now))       return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    if (sameDay(d, yesterday)) return 'ontem';
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function displayName(ticket) {
    if (ticket.customer_name) return ticket.customer_name;
    const digits = (ticket.phone_number ?? '').replace(/\D/g, '');
    if (digits.length === 13) {
        return `(${digits.slice(2, 4)}) ${digits.slice(4, 9)}-${digits.slice(9)}`;
    }
    return ticket.phone_number ?? '';
}

function initials(name) {
    if (!name?.trim()) return '?';
    const parts = name.trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : parts[0].slice(0, 2).toUpperCase();
}

onMounted(() => {
    pollTimer = setInterval(pollInbox, POLL_MS);
});

onUnmounted(() => {
    clearInterval(pollTimer);
});
</script>

<template>
    <div class="shell">
        <AppSidebar active="whatsapp" />

        <!-- painel esquerdo: lista -->
        <div class="inbox-panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="dot" />
                    <div>
                        <h1>Atendimento</h1>
                        <p>{{ date }}</p>
                    </div>
                </div>
                <span class="role-badge">WhatsApp</span>
            </div>

            <nav class="tabs">
                <button
                    v-for="tab in tabDefs"
                    :key="tab.key"
                    type="button"
                    class="tab"
                    :class="{ 'tab--active': activeTab === tab.key }"
                    @click="onTabChange(tab.key)"
                >
                    {{ tab.label }}
                    <span class="tab-badge">{{ tabCount(tab.key) }}</span>
                </button>
            </nav>

            <div class="search-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
                </svg>
                <input
                    v-model="search"
                    type="search"
                    class="search-input"
                    placeholder="Pesquisar por nome ou número..."
                    aria-label="Pesquisar nome"
                />
            </div>

            <div v-if="filteredList.length === 0" class="empty-list">
                <p v-if="search.trim()">Nenhum contato encontrado para "{{ search.trim() }}"</p>
                <p v-else>Nenhum chamado nesta aba</p>
            </div>

            <ul v-else class="contact-list">
                <WaContactRow
                    v-for="ticket in filteredList"
                    :key="ticket.id"
                    :ticket="ticket"
                    :selected="selectedId === ticket.id"
                    :show-novo-badge="activeTab === 'triage'"
                    :show-atender-button="activeTab === 'triage'"
                    :time-label="formatTime(ticket.updated_at)"
                    @select="onSelect"
                    @accept="handleAccept"
                />
            </ul>
        </div>

        <!-- painel direito: detalhe -->
        <div class="detail-panel" :class="{ 'detail-panel--chat': selectedTicket && selectedTicket.status !== 'triage' }">

            <!-- vazio -->
            <div v-if="!selectedId || !selectedTicket" class="detail-empty">
                <div class="detail-logo">
                    <img :src="`/img/logo.png`" alt="4Foods" />
                </div>
                <p>Selecione um ticket para iniciar o atendimento</p>
            </div>

            <!-- triagem selecionada -->
            <div v-else-if="selectedTicket.status === 'triage'" class="triage-detail">
                <div class="triage-detail-head">
                    <span class="triage-avatar">{{ initials(selectedTicket.customer_name) }}</span>
                    <div class="triage-contact-info">
                        <strong>{{ displayName(selectedTicket) }}</strong>
                        <span>{{ selectedTicket.phone_number }}</span>
                    </div>
                </div>

                <div v-if="selectedTicket.last_message" class="triage-preview">
                    <p class="triage-preview-label">Última mensagem</p>
                    <p class="triage-preview-text">{{ selectedTicket.last_message }}</p>
                </div>

                <p v-if="acceptError" class="triage-error">{{ acceptError }}</p>

                <div class="triage-actions">
                    <button
                        type="button"
                        class="btn-atender-detail"
                        :disabled="isAccepting"
                        @click="handleAccept(selectedTicket.id)"
                    >
                        {{ isAccepting ? 'Atendendo…' : 'Atender' }}
                    </button>
                </div>
            </div>

            <!-- chat (in_progress ou closed) -->
            <WaChatPanel
                v-else
                :ticket="selectedTicket"
                :messages="chatMessages"
                :loading="chatLoading"
                @message-sent="(msg) => chatMessages.push(msg)"
            />
        </div>
    </div>
</template>

<style scoped src="./styles/Inbox.css"></style>
