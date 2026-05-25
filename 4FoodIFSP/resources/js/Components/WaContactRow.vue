<script setup>
defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    selected:           { type: Boolean, default: false },
    showNovoBadge:      { type: Boolean, default: false },
    showAtenderButton:  { type: Boolean, default: false },
    timeLabel:          { type: String, required: true },
});

const emit = defineEmits(['select', 'accept']);

function displayName(ticket) {
    if (ticket.customer_name) return ticket.customer_name;

    const phone = ticket.phone_number ?? '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length === 13) {
        return `(${digits.slice(2, 4)}) ${digits.slice(4, 9)}-${digits.slice(9)}`;
    }
    return phone;
}

function initials(ticket) {
    const name = ticket.customer_name ?? '';
    if (!name.trim()) return '?';
    const parts = name.trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : parts[0].slice(0, 2).toUpperCase();
}
</script>

<template>
    <li class="contact-row-item">
        <button
            type="button"
            class="contact-row"
            :class="{ 'contact-row--selected': selected }"
            @click="emit('select', ticket.id)"
        >
            <span class="avatar">{{ initials(ticket) }}</span>
            <span class="body">
                <span class="head">
                    <span class="name">{{ displayName(ticket) }}</span>
                    <span class="meta">
                        <span v-if="showNovoBadge" class="badge-novo">Novo</span>
                        <span class="time">{{ timeLabel }}</span>
                    </span>
                </span>
                <span class="preview">{{ ticket.last_message }}</span>
            </span>
        </button>
        <button
            v-if="showAtenderButton"
            type="button"
            class="btn-atender-inline"
            @click.stop="emit('accept', ticket.id)"
        >
            Atender
        </button>
    </li>
</template>

<style scoped src="./styles/WaContactRow.css"></style>
