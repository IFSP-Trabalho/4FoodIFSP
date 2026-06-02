<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import OrderCard from '../../Components/OrderCard.vue';
import CancelOrderPanel from '../../Components/CancelOrderPanel.vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    history: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            date_from: '',
            date_to: '',
        }),
    },
    date: {
        type: String,
        required: true,
    },
});

// A cozinha pode acompanhar e preparar pedidos, mas não cancelá-los.
const canCancel = computed(() => usePage().props.auth?.user?.role !== 'kitchen');

const activeTab = ref('live');
const search = ref('');
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const statusKeys = ['pending', 'in_progress', 'ready'];
const localOrders = ref(cloneOrders(props.orders));
const dragState = ref({ orderUuid: null, fromStatus: null });
const dragOverStatus = ref(null);
const cancelTarget = ref(null);

const soundEnabled = ref(localStorage.getItem('4food_orders_sound') === '1');
const knownPendingIds = new Set();
let pollInitialized = false;
let pollingInterval = null;
let notifyAudio = null;
let audioUnlocked = false;

watch(
    () => props.filters,
    (nextFilters) => {
        dateFrom.value = nextFilters.date_from;
        dateTo.value = nextFilters.date_to;
    }
);

watch(
    () => props.orders,
    (nextOrders) => {
        localOrders.value = cloneOrders(nextOrders);
    }
);

function cloneOrders(source) {
    return statusKeys.reduce((accumulator, status) => {
        accumulator[status] = (source[status] ?? []).map((order) => ({
            ...order,
            items: (order.items ?? []).map((item) => ({ ...item })),
        }));
        return accumulator;
    }, {});
}

function filterBySearch(list) {
    if (!search.value) {
        return list;
    }

    const term = search.value.toLowerCase();

    return list.filter((order) => order.items.some((item) => item.name.toLowerCase().includes(term)));
}

const pendente = computed(() => filterBySearch(localOrders.value.pending ?? []));
const preparando = computed(() => filterBySearch(localOrders.value.in_progress ?? []));
const finalizados = computed(() => filterBySearch(localOrders.value.ready ?? []));

function persistStatus(orderUuid, status) {
    router.patch(`/admin/orders/${orderUuid}/status`, { status }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function moveOrder(orderUuid, fromStatus, toStatus) {
    if (fromStatus === toStatus) {
        return false;
    }

    const sourceList = localOrders.value[fromStatus] ?? [];
    const targetList = localOrders.value[toStatus] ?? [];
    const sourceIndex = sourceList.findIndex((order) => order.uuid === orderUuid);

    if (sourceIndex === -1) {
        return false;
    }

    const [order] = sourceList.splice(sourceIndex, 1);
    targetList.unshift(order);

    return true;
}

function advanceStatus(orderUuid, currentStatus) {
    if (currentStatus === 'pending') {
        if (moveOrder(orderUuid, 'pending', 'in_progress')) {
            persistStatus(orderUuid, 'in_progress');
        }
        return;
    }

    if (currentStatus === 'in_progress') {
        if (moveOrder(orderUuid, 'in_progress', 'ready')) {
            persistStatus(orderUuid, 'ready');
        }
    }
}

function findOrder(orderUuid) {
    for (const status of statusKeys) {
        const found = (localOrders.value[status] ?? []).find((order) => order.uuid === orderUuid);
        if (found) {
            return found;
        }
    }
    return null;
}

function toggleItem(orderUuid, { itemId, completed }) {
    const order = findOrder(orderUuid);
    const item = order?.items.find((entry) => entry.id === itemId);
    if (!item) {
        return;
    }

    item.completed = completed;

    router.patch(`/admin/orders/${orderUuid}/items/${itemId}`, { completed }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function openCancel(order) {
    cancelTarget.value = order;
}

function closeCancel() {
    cancelTarget.value = null;
}

function canDropOn(status) {
    return status !== 'ready';
}

function onDragStart(orderUuid, fromStatus, event) {
    dragState.value = { orderUuid, fromStatus };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', orderUuid);
}

function onDragEnd() {
    dragState.value = { orderUuid: null, fromStatus: null };
    dragOverStatus.value = null;
}

function onColumnDragOver(status, event) {
    if (!canDropOn(status)) {
        return;
    }

    event.preventDefault();
    dragOverStatus.value = status;
}

function onColumnDragLeave(status) {
    if (dragOverStatus.value === status) {
        dragOverStatus.value = null;
    }
}

function onColumnDrop(status, event) {
    if (!canDropOn(status)) {
        return;
    }

    event.preventDefault();

    const { orderUuid, fromStatus } = dragState.value;
    if (!orderUuid || !fromStatus) {
        return;
    }

    if (moveOrder(orderUuid, fromStatus, status)) {
        persistStatus(orderUuid, status);
    }

    onDragEnd();
}

function filterHistory() {
    router.get('/admin/orders/history', {
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function statusLabel(status) {
    if (status === 'ready') {
        return 'Pronto';
    }

    return 'Cancelado';
}

// ── Bell / sound ──

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
    localStorage.setItem('4food_orders_sound', soundEnabled.value ? '1' : '0');

    if (soundEnabled.value) {
        unlockAudio();
        if (!pollInitialized) {
            pollOnce();
        }
    }
}

async function playNewOrderSound() {
    try {
        const audio = ensureAudio();
        audio.currentTime = 0;
        await audio.play();
    } catch {
        // NotAllowedError or missing file — ignore silently
    }
}

// ── Polling ──

async function pollOnce() {
    try {
        const { data } = await axios.get('/admin/orders/poll');
        const pendingIds = data.pending_ids ?? [];

        if (!pollInitialized) {
            pendingIds.forEach((id) => knownPendingIds.add(id));
            pollInitialized = true;
            return;
        }

        const newIds = pendingIds.filter((id) => !knownPendingIds.has(id));

        if (newIds.length > 0) {
            newIds.forEach((id) => knownPendingIds.add(id));
            if (soundEnabled.value) {
                await playNewOrderSound();
            }
            router.reload({ only: ['orders'], preserveScroll: true });
        }
    } catch {
        // network error — ignore
    }
}

function startPolling() {
    if (pollingInterval !== null) return;
    pollingInterval = setInterval(pollOnce, 8000);
    if (!pollInitialized) {
        pollOnce();
    }
}

function stopPolling() {
    if (pollingInterval !== null) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

function syncPollingState() {
    const shouldPoll = activeTab.value === 'live' && document.visibilityState === 'visible';
    if (shouldPoll) {
        startPolling();
    } else {
        stopPolling();
    }
}

watch(activeTab, syncPollingState);

// Desbloqueia o áudio na primeira interação, caso o som já esteja ativado pelo localStorage.
function primeAudioOnInteraction() {
    if (soundEnabled.value) unlockAudio();
    window.removeEventListener('pointerdown', primeAudioOnInteraction);
    window.removeEventListener('keydown', primeAudioOnInteraction);
}

onMounted(() => {
    document.addEventListener('visibilitychange', syncPollingState);
    window.addEventListener('pointerdown', primeAudioOnInteraction);
    window.addEventListener('keydown', primeAudioOnInteraction);
    syncPollingState();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', syncPollingState);
    window.removeEventListener('pointerdown', primeAudioOnInteraction);
    window.removeEventListener('keydown', primeAudioOnInteraction);
    stopPolling();
});
</script>

<template>
    <div class="shell">
        <AppSidebar active="orders" />
        <div class="main">
            <AppTopbar title="Pedidos" :subtitle="props.date" />

            <div class="content">
                <div class="orders-toolbar">
                    <div class="tabs">
                        <button
                            type="button"
                            :class="{ active: activeTab === 'live' }"
                            @click="activeTab = 'live'"
                        >
                            Pedidos ao vivo
                        </button>
                        <button
                            type="button"
                            :class="{ active: activeTab === 'history' }"
                            @click="activeTab = 'history'"
                        >
                            Historico de pedidos
                        </button>
                    </div>

                    <div class="toolbar-right">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Pesquisar Prato"
                            class="search-input"
                        >
                        <button
                            type="button"
                            class="bell-btn"
                            :class="soundEnabled ? 'bell-btn--on' : 'bell-btn--off'"
                            :aria-pressed="soundEnabled"
                            :aria-label="soundEnabled ? 'Desativar notificações sonoras' : 'Ativar notificações sonoras'"
                            :title="soundEnabled ? 'Notificações ativadas' : 'Notificações desativadas'"
                            @click="toggleSound"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3a6 6 0 0 0-6 6v3.6L4.4 16a1 1 0 0 0 .8 1.6h13.6a1 1 0 0 0 .8-1.6L18 12.6V9a6 6 0 0 0-6-6Zm0 19a3 3 0 0 0 2.82-2h-5.64A3 3 0 0 0 12 22Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-if="activeTab === 'live'" class="kanban">
                    <div
                        class="kanban-col"
                        :class="{ 'kanban-col--drop-target': dragOverStatus === 'pending' }"
                        @dragover="onColumnDragOver('pending', $event)"
                        @dragleave="onColumnDragLeave('pending')"
                        @drop="onColumnDrop('pending', $event)"
                    >
                        <div class="col-header">
                            <span class="col-title">Pendente</span>
                            <span class="col-badge">{{ pendente.length }}</span>
                        </div>
                        <div class="col-body">
                            <div
                                v-for="order in pendente"
                                :key="order.uuid"
                                class="order-drag-wrapper"
                                draggable="true"
                                @dragstart="onDragStart(order.uuid, 'pending', $event)"
                                @dragend="onDragEnd"
                            >
                                <OrderCard
                                    :order="order"
                                    status="pending"
                                    :can-cancel="canCancel"
                                    @advance="advanceStatus(order.uuid, 'pending')"
                                    @toggle-item="toggleItem(order.uuid, $event)"
                                    @cancel="openCancel(order)"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="kanban-col"
                        :class="{ 'kanban-col--drop-target': dragOverStatus === 'in_progress' }"
                        @dragover="onColumnDragOver('in_progress', $event)"
                        @dragleave="onColumnDragLeave('in_progress')"
                        @drop="onColumnDrop('in_progress', $event)"
                    >
                        <div class="col-header">
                            <span class="col-title">Preparando</span>
                            <span class="col-badge">{{ preparando.length }}</span>
                        </div>
                        <div class="col-body">
                            <div
                                v-for="order in preparando"
                                :key="order.uuid"
                                class="order-drag-wrapper"
                                draggable="true"
                                @dragstart="onDragStart(order.uuid, 'in_progress', $event)"
                                @dragend="onDragEnd"
                            >
                                <OrderCard
                                    :order="order"
                                    status="in_progress"
                                    :can-cancel="canCancel"
                                    @advance="advanceStatus(order.uuid, 'in_progress')"
                                    @toggle-item="toggleItem(order.uuid, $event)"
                                    @cancel="openCancel(order)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="kanban-col">
                        <div class="col-header">
                            <span class="col-title">Finalizados</span>
                            <span class="col-badge">{{ finalizados.length }}</span>
                        </div>
                        <div class="col-body">
                            <OrderCard
                                v-for="order in finalizados"
                                :key="order.uuid"
                                :order="order"
                                status="ready"
                                @advance="advanceStatus(order.uuid, 'ready')"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'history'" class="history-view">
                    <form class="history-filters" @submit.prevent="filterHistory">
                        <label>
                            De
                            <input v-model="dateFrom" type="date">
                        </label>
                        <label>
                            Ate
                            <input v-model="dateTo" type="date">
                        </label>
                        <button type="submit" class="filter-btn">Filtrar</button>
                    </form>

                    <div class="history-table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Mesa</th>
                                    <th>Itens</th>
                                    <th>Status</th>
                                    <th>Horario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in props.history" :key="entry.id">
                                    <td>{{ entry.id }}</td>
                                    <td>{{ entry.mesa }}</td>
                                    <td>{{ entry.items.join(', ') }}</td>
                                    <td>
                                        <span
                                            class="status-badge"
                                            :class="entry.status === 'ready' ? 'status-ready' : 'status-cancelled'"
                                        >
                                            {{ statusLabel(entry.status) }}
                                        </span>
                                        <span
                                            v-if="entry.status === 'cancelled' && entry.cancel_reason"
                                            class="cancel-reason"
                                            :title="entry.cancel_reason"
                                        >
                                            {{ entry.cancel_reason }}
                                        </span>
                                    </td>
                                    <td>{{ entry.time }}</td>
                                </tr>
                                <tr v-if="props.history.length === 0">
                                    <td colspan="5" class="empty-row">Nenhum pedido no periodo selecionado.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <CancelOrderPanel
            v-if="cancelTarget"
            :order="cancelTarget"
            @close="closeCancel"
        />
    </div>
</template>

<style scoped src="./styles/Orders.css"></style>
