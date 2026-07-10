<script setup>
import axios from 'axios';
import { ref, onMounted } from 'vue';
import { formatPriceBRL } from '../utils/formatPrice.js';

const props = defineProps({
    ticket: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const loading = ref(true);
const errorMsg = ref(null);
const data = ref({ customer_name: '', phone: '', count: 0, total: 0, orders: [] });

onMounted(async () => {
    try {
        const res = await axios.get(`/whatsapp/inbox/tickets/${props.ticket.id}/orders`);
        data.value = res.data;
    } catch (e) {
        errorMsg.value = 'Não foi possível carregar os pedidos.';
    } finally {
        loading.value = false;
    }
});

function statusClass(status) {
    if (status === 'ready') return 'co-badge--done';
    if (status === 'cancelled') return 'co-badge--cancelled';
    return 'co-badge--progress';
}

function formatWhen(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

function close() {
    emit('close');
}
</script>

<template>
    <div class="admin-modal-overlay" @click.self="close" />
    <div class="admin-modal" role="dialog" aria-modal="true">
        <div class="admin-modal-head">
            <h3>Pedidos de {{ data.customer_name || ticket.customer_name || 'Cliente' }}</h3>
            <p>{{ data.phone || ticket.phone_number }}</p>
        </div>

        <div class="admin-modal-form">
            <div class="admin-modal-body">
                <p v-if="loading" class="co-empty">Carregando pedidos…</p>
                <p v-else-if="errorMsg" class="admin-modal-error">{{ errorMsg }}</p>
                <p v-else-if="data.orders.length === 0" class="co-empty">
                    Este contato ainda não fez nenhum pedido.
                </p>

                <template v-else>
                    <div class="co-summary">
                        <div>
                            <span class="co-summary-label">Pedidos</span>
                            <strong class="co-summary-value">{{ data.count }}</strong>
                        </div>
                        <div class="co-summary-total">
                            <span class="co-summary-label">Total</span>
                            <strong class="co-summary-value co-summary-value--accent">{{ formatPriceBRL(data.total) }}</strong>
                        </div>
                    </div>

                    <ul class="co-list">
                        <li v-for="order in data.orders" :key="order.id" class="co-item">
                            <div class="co-item-head">
                                <span class="co-item-id">#{{ order.id }}</span>
                                <span class="co-badge" :class="statusClass(order.status)">{{ order.status_label }}</span>
                                <span class="co-item-when">{{ formatWhen(order.created_at) }}</span>
                            </div>
                            <ul class="co-item-dishes">
                                <li v-for="(it, i) in order.items" :key="i">
                                    <span class="co-item-qty">{{ it.qty }}x</span> {{ it.name }}
                                </li>
                            </ul>
                            <div class="co-item-total">{{ formatPriceBRL(order.total) }}</div>
                        </li>
                    </ul>
                </template>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="secondary" @click="close">Fechar</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.co-empty {
    margin: 0;
    padding: 8px 2px;
    font-size: 13px;
    color: var(--fg-9aa1ad);
}

.co-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: var(--bg-f6f7f9);
    border: 1px solid var(--bd-eceef0);
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 12px;
}

.co-summary-total {
    text-align: right;
}

.co-summary-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--fg-7b7f89);
    font-weight: 600;
}

.co-summary-value {
    font-size: 20px;
    color: var(--fg-17181e);
}

.co-summary-value--accent {
    color: var(--fg-993c1d);
}

.co-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.co-item {
    border: 1px solid var(--bd-eceef0);
    border-radius: 10px;
    padding: 10px 12px;
    background: var(--bg-ffffff);
}

.co-item-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.co-item-id {
    font-size: 12px;
    font-weight: 700;
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
    color: var(--fg-17181e);
}

.co-item-when {
    margin-left: auto;
    font-size: 11px;
    color: var(--fg-9aa1ad);
}

.co-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
}

.co-badge--progress {
    background: var(--bg-fff4ed);
    color: var(--fg-993c1d);
}

.co-badge--done {
    background: var(--bg-ecf8f0);
    color: var(--fg-256f3d);
}

.co-badge--cancelled {
    background: var(--bg-f3f4f6);
    color: var(--fg-6b7280);
}

.co-item-dishes {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.co-item-dishes li {
    font-size: 13px;
    color: var(--fg-1f242e);
}

.co-item-qty {
    font-weight: 600;
    color: var(--fg-993c1d);
}

.co-item-total {
    margin-top: 6px;
    text-align: right;
    font-size: 13px;
    font-weight: 700;
    color: var(--fg-17181e);
}
</style>
