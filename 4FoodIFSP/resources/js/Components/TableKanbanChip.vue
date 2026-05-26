<script setup>
defineProps({
    table: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    accent: { type: String, default: '#17181e' },
});

const emit = defineEmits(['click']);

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <button
        type="button"
        class="kanban-chip"
        :class="{ 'kanban-chip--selected': selected }"
        :style="{ '--chip-accent': accent }"
        @click="emit('click', table)"
    >
        <span class="kc-main">
            <span class="kc-number">{{ table.number }}</span>
            <span v-if="table.label && table.label !== 'Mesa ' + table.number" class="kc-label">
                {{ table.label }}
            </span>
        </span>

        <span v-if="table.total_open" class="kc-total">{{ table.total_open }}</span>
        <span v-else-if="table.session_started_at" class="kc-meta">
            {{ formatTime(table.session_started_at) }}
            <template v-if="table.orders_count > 0"> · {{ table.orders_count }}p</template>
        </span>
    </button>
</template>

<style scoped src="./styles/TableKanbanChip.css"></style>
