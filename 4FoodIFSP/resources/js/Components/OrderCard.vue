<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['advance', 'toggle-item', 'cancel']);

const actionLabel = computed(() => ({
    pending: 'Preparar pedido',
    in_progress: 'Finalizar pedido',
    ready: 'Pedido finalizado',
}[props.status]));

// Itens só recebem checkbox/conclusão enquanto o pedido está em preparo.
const showChecklist = computed(() => props.status === 'in_progress');

const allItemsDone = computed(() => props.order.items.every((item) => item.completed));

// Finalizar (in_progress -> ready) só é permitido com todos os itens concluídos.
const actionDisabled = computed(() => {
    if (props.status === 'ready') {
        return true;
    }
    if (props.status === 'in_progress') {
        return !allItemsDone.value;
    }
    return false;
});

const cardClass = computed(() => ({
    'card--pending': props.status === 'pending',
    'card--in-progress': props.status === 'in_progress',
    'card--ready': props.status === 'ready',
}));

function itemClass(item) {
    if (props.status === 'ready') {
        return 'item--done';
    }
    return item.completed ? 'item--done' : '';
}
</script>

<template>
    <div class="order-card" :class="cardClass">
        <div class="card-head">
            <span class="order-id">#{{ order.id }}</span>
            <span v-if="status === 'ready'" class="badge-feito">FEITO</span>
        </div>

        <p class="mesa-label">{{ order.mesa }}</p>

        <ul class="items-list">
            <li
                v-for="item in order.items"
                :key="item.id ?? `${order.id}-${item.name}`"
                class="item-row"
                :class="itemClass(item)"
            >
                <label v-if="showChecklist" class="item-check">
                    <input
                        type="checkbox"
                        :checked="item.completed"
                        @change="emit('toggle-item', { itemId: item.id, completed: $event.target.checked })"
                    >
                    <span class="item-qty">{{ item.qty }}x</span>
                    <span class="item-name">{{ item.name }}</span>
                </label>
                <template v-else>
                    <span class="item-qty">{{ item.qty }}x</span>
                    <span class="item-name">{{ item.name }}</span>
                </template>
            </li>
        </ul>

        <p v-if="order.note_summary" class="note">
            {{ order.note_summary }}
        </p>

        <p v-if="status === 'in_progress' && !allItemsDone" class="checklist-hint">
            Conclua todos os itens para finalizar.
        </p>

        <button
            type="button"
            class="action-btn"
            :class="`action-btn--${status}`"
            :disabled="actionDisabled"
            @click="!actionDisabled && emit('advance', order.id)"
        >
            {{ actionLabel }}
        </button>

        <button
            v-if="status !== 'ready'"
            type="button"
            class="cancel-btn"
            @click="emit('cancel', order)"
        >
            Cancelar pedido
        </button>
    </div>
</template>

<style scoped src="./styles/OrderCard.css"></style>
