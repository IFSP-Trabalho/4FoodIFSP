<script setup>
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import WaChatPanel from '../../Components/WaChatPanel.vue';
import WaContactRow from '../../Components/WaContactRow.vue';

const props = defineProps({
    tickets:        { type: Object, required: true },
    date:           { type: String, required: true },
    authUserId:     { type: String, default: null },
    closureReasons: { type: Array,  default: () => [] },
    focusTicketId:  { type: String, default: null },
});

const activeTab   = ref('in_progress');
const search      = ref('');
const selectedId  = ref(null);
const chatMessages = ref([]);
const chatLoading  = ref(false);
const isAccepting  = ref(false);
const acceptError  = ref(null);

const showCloseModal  = ref(false);
const pendingCloseId  = ref(null);
const closeReasonId   = ref('');
const closeSummary    = ref('');
const closeError      = ref(null);
const isClosing       = ref(false);

const localClearedIds = ref([]);

const soundEnabled = ref(localStorage.getItem('4food_inbox_sound') === '1');
const knownTriageIds = new Set();
let knownUnread = new Map();
let pollInitialized = false;
let notifyAudio = null;
let audioUnlocked = false;

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
    let list = currentList.value;
    if (term) {
        list = list.filter((t) => {
            const name  = (t.customer_name ?? '').toLowerCase();
            const phone = (t.phone_number  ?? '').toLowerCase();
            return name.includes(term) || phone.includes(term);
        });
    }
    return list.map((t) =>
        localClearedIds.value.includes(t.id)
            ? { ...t, unread_count: 0, is_unread: false }
            : t
    );
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
    if (!localClearedIds.value.includes(id)) {
        localClearedIds.value = [...localClearedIds.value, id];
    }
    // Carrega o histórico completo em qualquer aba (inclusive triagem).
    await fetchMessages(id);
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
        window.alert(e.response?.data?.message ?? 'Erro ao atender ticket.');
    } finally {
        isAccepting.value = false;
    }
}

function handleClose(ticketId) {
    pendingCloseId.value = ticketId;
    closeReasonId.value  = '';
    closeSummary.value   = '';
    closeError.value     = null;
    showCloseModal.value = true;
}

function cancelClose() {
    showCloseModal.value = false;
    pendingCloseId.value = null;
}

async function confirmClose() {
    if (!closeReasonId.value) {
        closeError.value = 'Selecione um motivo de fechamento.';
        return;
    }
    isClosing.value  = true;
    closeError.value = null;
    try {
        await axios.patch(`/whatsapp/inbox/tickets/${pendingCloseId.value}/close`, {
            closure_reason_id: closeReasonId.value,
            summary:           closeSummary.value || null,
        });
        showCloseModal.value = false;
        activeTab.value      = 'closed';
        router.reload({ only: ['tickets'], preserveScroll: true });
    } catch (e) {
        closeError.value = e.response?.data?.message ?? 'Erro ao fechar atendimento.';
    } finally {
        isClosing.value = false;
    }
}

async function handleReopen(ticketId) {
    try {
        await axios.patch(`/whatsapp/inbox/tickets/${ticketId}/reopen`);
        activeTab.value = 'in_progress';
        router.reload({ only: ['tickets'], preserveScroll: true });
    } catch (e) {
        window.alert(e.response?.data?.message ?? 'Erro ao reabrir atendimento.');
    }
}

async function handleReturnTriage(ticketId) {
    try {
        await axios.patch(`/whatsapp/inbox/tickets/${ticketId}/triage`);
        activeTab.value    = 'triage';
        selectedId.value   = null;
        chatMessages.value = [];
        router.reload({ only: ['tickets'], preserveScroll: true });
    } catch (e) {
        window.alert(e.response?.data?.message ?? 'Erro ao retornar ticket à triagem.');
    }
}

async function handleMarkUnread(ticketId) {
    try {
        await axios.patch(`/whatsapp/inbox/tickets/${ticketId}/unread`);
        localClearedIds.value = localClearedIds.value.filter((id) => id !== ticketId);
        selectedId.value   = null;
        chatMessages.value = [];
        router.reload({ only: ['tickets'], preserveScroll: true });
    } catch (e) {
        window.alert(e.response?.data?.message ?? 'Erro ao marcar como não lido.');
    }
}

function ensureAudio() {
    if (!notifyAudio) {
        notifyAudio = new Audio('/sounds/new-order.mp3');
        notifyAudio.preload = 'auto';
    }
    return notifyAudio;
}

// Desbloqueia o áudio dentro de um gesto do usuário (política de autoplay do navegador):
// toca mudo uma vez e pausa, liberando reproduções posteriores disparadas pelo timer.
function unlockAudio() {
    if (audioUnlocked) return;
    const audio = ensureAudio();
    audio.muted = true;
    audio.play()
        .then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            audioUnlocked = true;
        })
        .catch(() => {
            audio.muted = false;
        });
}

function toggleSound() {
    soundEnabled.value = !soundEnabled.value;
    localStorage.setItem('4food_inbox_sound', soundEnabled.value ? '1' : '0');
    if (soundEnabled.value) {
        unlockAudio();
    }
}

async function playNewTicketSound() {
    try {
        const audio = ensureAudio();
        audio.currentTime = 0;
        await audio.play();
    } catch {
        // NotAllowedError ou arquivo ausente — ignora silenciosamente
    }
}

async function pollInbox() {
    if (document.visibilityState !== 'visible') return;
    try {
        const { data } = await axios.get('/whatsapp/inbox/poll');
        const triageIds = data.triage_ids ?? [];
        const unread = new Map(Object.entries(data.unread_counts ?? {}).map(([id, n]) => [id, Number(n)]));

        if (!pollInitialized) {
            triageIds.forEach((id) => knownTriageIds.add(id));
            knownUnread = unread;
            pollInitialized = true;
        } else {
            // Novo chamado na triagem.
            let shouldPlay = triageIds.some((id) => !knownTriageIds.has(id));
            triageIds.forEach((id) => knownTriageIds.add(id));

            // Mensagem nova (unread_count aumentou) em um chamado que NÃO está aberto.
            const reappear = [];
            for (const [id, count] of unread) {
                if (id === selectedId.value) continue;
                if (count > (knownUnread.get(id) ?? 0)) {
                    shouldPlay = true;
                    reappear.push(id);
                }
            }
            knownUnread = unread;

            // Reexibe o badge de não-lido mesmo que o chamado já tenha sido aberto antes.
            if (reappear.length) {
                localClearedIds.value = localClearedIds.value.filter((id) => !reappear.includes(id));
            }

            if (shouldPlay && soundEnabled.value) {
                await playNewTicketSound();
            }
        }

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
    const isChat = ['in_progress', 'closed', 'triage'].includes(ticket.status);
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

function focusFromQuery() {
    if (!props.focusTicketId) return;
    const ticket = allTickets.value.find((t) => t.id === props.focusTicketId);
    if (!ticket) return;
    activeTab.value = ticket.status;
    onSelect(ticket.id);
}

function closeDetail() {
    selectedId.value   = null;
    chatMessages.value = [];
    acceptError.value  = null;
}

function onKeydown(event) {
    // Qualquer tecla é um gesto válido para desbloquear o áudio.
    if (soundEnabled.value) unlockAudio();

    if (event.key !== 'Escape') return;

    // Fecha o modal de fechamento, se estiver aberto; senão, fecha a tela do chamado.
    if (showCloseModal.value) {
        cancelClose();
    } else if (selectedId.value) {
        closeDetail();
    }
}

// Desbloqueia o áudio na primeira interação, caso o som já esteja ativado pelo localStorage.
function primeAudioOnInteraction() {
    if (soundEnabled.value) unlockAudio();
    window.removeEventListener('pointerdown', primeAudioOnInteraction);
}

onMounted(() => {
    focusFromQuery();
    pollTimer = setInterval(pollInbox, POLL_MS);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('pointerdown', primeAudioOnInteraction);
});

onUnmounted(() => {
    clearInterval(pollTimer);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('pointerdown', primeAudioOnInteraction);
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
                <div class="panel-head-right">
                    <button
                        type="button"
                        class="bell-btn"
                        :class="soundEnabled ? 'bell-btn--on' : 'bell-btn--off'"
                        :aria-pressed="soundEnabled"
                        :aria-label="soundEnabled ? 'Desativar aviso sonoro' : 'Ativar aviso sonoro'"
                        :title="soundEnabled ? 'Aviso sonoro ativado' : 'Aviso sonoro desativado'"
                        @click="toggleSound"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3a6 6 0 0 0-6 6v3.6L4.4 16a1 1 0 0 0 .8 1.6h13.6a1 1 0 0 0 .8-1.6L18 12.6V9a6 6 0 0 0-6-6Zm0 19a3 3 0 0 0 2.82-2h-5.64A3 3 0 0 0 12 22Z" />
                        </svg>
                    </button>
                    <span class="role-badge">WhatsApp</span>
                </div>
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

            <div class="inbox-body">
                <label class="wa-search">
                    <svg class="wa-search-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        class="wa-search-input"
                        placeholder="Pesquisar por nome ou número..."
                        aria-label="Pesquisar nome"
                    />
                </label>

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
        </div>

        <!-- painel direito: detalhe -->
        <div class="detail-panel" :class="{ 'detail-panel--chat': !!selectedTicket }">

            <!-- vazio -->
            <div v-if="!selectedId || !selectedTicket" class="detail-empty">
                <div class="detail-logo">
                    <img :src="`/img/logo.png`" alt="4Foods" />
                </div>
                <p>Selecione um ticket para iniciar o atendimento</p>
            </div>

            <!-- chat completo (na triagem, o composer vira o botão Atender) -->
            <WaChatPanel
                v-else
                :ticket="selectedTicket"
                :messages="chatMessages"
                :loading="chatLoading"
                :accepting="isAccepting"
                @accept="handleAccept(selectedTicket.id)"
                @message-sent="(msg) => chatMessages.push(msg)"
                @close="handleClose(selectedTicket.id)"
                @reopen="handleReopen(selectedTicket.id)"
                @return-triage="handleReturnTriage(selectedTicket.id)"
                @mark-unread="handleMarkUnread(selectedTicket.id)"
            />
        </div>
    </div>

    <!-- modal de fechamento -->
    <div v-if="showCloseModal" class="close-modal-overlay" @click.self="cancelClose">
        <div class="close-modal-dialog">
            <h3>Fechar atendimento</h3>

            <div class="close-modal-field">
                <label class="close-modal-label">Motivo <span class="close-modal-required">*</span></label>
                <select v-model="closeReasonId" class="close-modal-select">
                    <option value="">Selecione...</option>
                    <option v-for="r in closureReasons" :key="r.id" :value="r.id">
                        {{ r.name }}
                    </option>
                </select>
                <p v-if="closureReasons.length === 0" class="close-modal-hint">
                    Nenhum motivo cadastrado. Configure em Cadastros → Motivos WA.
                </p>
            </div>

            <div class="close-modal-field">
                <label class="close-modal-label">
                    Resumo <span class="close-modal-optional">(opcional)</span>
                </label>
                <textarea
                    v-model="closeSummary"
                    class="close-modal-textarea"
                    rows="3"
                    placeholder="Resumo do atendimento..."
                    maxlength="4096"
                />
            </div>

            <p v-if="closeError" class="close-modal-error">{{ closeError }}</p>

            <footer class="close-modal-actions">
                <button type="button" class="cm-btn cm-btn--secondary" :disabled="isClosing" @click="cancelClose">
                    Cancelar
                </button>
                <button
                    type="button"
                    class="cm-btn cm-btn--danger"
                    :disabled="isClosing || !closeReasonId"
                    @click="confirmClose"
                >
                    {{ isClosing ? 'Fechando...' : 'Fechar atendimento' }}
                </button>
            </footer>
        </div>
    </div>
</template>

<style scoped src="./styles/Inbox.css"></style>
