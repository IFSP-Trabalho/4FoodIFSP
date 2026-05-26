<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        required: true,
        validator: (v) => ['salao', 'operacional'].includes(v),
    },
});
</script>

<template>
    <nav class="layout-switch" aria-label="Alternar layout de mesas">
        <Link
            href="/admin/mesas"
            class="layout-switch-btn"
            :class="{ 'layout-switch-btn--active': active === 'salao' }"
            preserve-scroll
        >
            Salão
        </Link>
        <Link
            href="/admin/mesas/operacional"
            class="layout-switch-btn"
            :class="{ 'layout-switch-btn--active': active === 'operacional' }"
            preserve-scroll
        >
            Operacional
        </Link>
    </nav>
</template>

<style scoped src="./styles/TablesLayoutSwitch.css"></style>
