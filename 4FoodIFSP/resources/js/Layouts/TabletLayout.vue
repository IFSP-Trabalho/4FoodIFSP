<script setup>
import './styles/TabletLayout.css';

defineProps({
    mesa: {
        type: Number,
        required: true,
    },
});
</script>

<template>
    <div class="tablet-layout theme-force-light">
        <header class="tablet-header">
            <span class="tablet-header-brand">4Food</span>
            <span class="tablet-header-sep">&middot;</span>
            <span class="tablet-header-mesa">Mesa {{ mesa }}</span>
        </header>
        <main class="tablet-content">
            <slot />
        </main>
    </div>
</template>
