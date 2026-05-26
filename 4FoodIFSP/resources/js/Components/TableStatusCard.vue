<script setup>
const props = defineProps({
    table: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['click']);

const statusLabel = {
    available:       'Disponível',
    in_use:          'Em uso',
    open_bill:       'Conta aberta',
    pending_release: 'Aguardando',
    inactive:        'Inativa',
};

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function handleClick() {
    if (props.table.status !== 'inactive') emit('click', props.table);
}
</script>

<template>
    <button
        type="button"
        class="table-card"
        :class="[
            `table-card--${table.status}`,
            { 'table-card--selected': selected },
        ]"
        :disabled="table.status === 'inactive'"
        @click="handleClick"
    >
        <span class="tc-top">
            <span class="tc-prefix">Mesa</span>
            <span class="tc-number">{{ table.number }}</span>
        </span>

        <span v-if="table.label && table.label !== 'Mesa ' + table.number" class="tc-label">
            {{ table.label }}
        </span>

        <span class="tc-status" :class="`tc-status--${table.status}`">
            {{ statusLabel[table.status] ?? table.status }}
        </span>

        <span v-if="table.total_open" class="tc-total">{{ table.total_open }}</span>

        <span
            v-else-if="table.session_started_at && table.status !== 'inactive'"
            class="tc-meta"
        >
            desde {{ formatTime(table.session_started_at) }}<template v-if="table.orders_count > 0"> · {{ table.orders_count }} ped.</template>
        </span>
    </button>
</template>

<style scoped src="./styles/TableStatusCard.css"></style>
