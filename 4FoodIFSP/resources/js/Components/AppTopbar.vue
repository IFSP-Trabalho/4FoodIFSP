<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        required: true,
    },
    roleBadge: {
        type: String,
        default: null,
    },
});

const roleLabels = {
    admin: 'Admin',
    kitchen: 'Cozinha',
    finance: 'Financeiro',
    waiter: 'Garçom',
    whatsapp_agent: 'Atendimento',
};

const page = usePage();
const displayBadge = computed(
    () => props.roleBadge ?? roleLabels[page.props.auth?.user?.role] ?? '',
);
</script>

<template>
    <header class="topbar">
        <div class="title-wrap">
            <span class="dot" />
            <div>
                <h1>{{ title }}</h1>
                <p>{{ subtitle }}</p>
            </div>
        </div>
        <span class="role-badge">{{ displayBadge }}</span>
    </header>
</template>

<style scoped src="./styles/AppTopbar.css"></style>
