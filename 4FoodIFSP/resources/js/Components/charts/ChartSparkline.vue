<script setup>
import { computed } from 'vue';

const props = defineProps({
    values: { type: Array, default: () => [] },
    color:  { type: String, default: '#10B981' },
});

const pathData = computed(() => {
    const v = props.values;
    if (v.length < 2) return '';
    const max   = Math.max(1, ...v);
    const range = max;
    const W = 80, H = 24;

    return v.map((val, i) => {
        const x = (i / (v.length - 1)) * W;
        const y = H - (Math.max(0, val) / range) * (H - 4) - 2;
        return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
});
</script>

<template>
    <svg
        v-if="values.length >= 2"
        viewBox="0 0 80 24"
        preserveAspectRatio="none"
        class="spark"
        aria-hidden="true"
    >
        <path
            :d="pathData"
            fill="none"
            :stroke="color"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />
    </svg>
</template>

<style scoped>
.spark {
    display: block;
    width: 100%;
    height: 24px;
    overflow: visible;
    opacity: 0.65;
}
</style>
