<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    accent: { type: String, default: '#993c1d' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <article class="kpi" :style="{ '--accent': accent }">
        <div class="kpi-head">
            <span class="kpi-dot" />
            <p class="kpi-label">{{ label }}</p>
        </div>
        <p class="kpi-value">{{ value }}</p>
        <p v-if="hint" class="kpi-hint">{{ hint }}</p>
    </article>
</template>

<style scoped>
.kpi {
    position: relative;
    background: #fff;
    border: 1px solid #eef0f2;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 1px 2px rgba(16, 17, 22, 0.04);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.kpi:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(16, 17, 22, 0.1);
}

.kpi-head {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 10px;
}

.kpi-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 16%, transparent);
}

.kpi-label {
    margin: 0;
    color: #7b7f89;
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 10px;
    font-weight: 500;
}

.kpi-value {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
    color: #17181e;
    line-height: 1.05;
    letter-spacing: -0.01em;
}

.kpi-hint {
    margin: 6px 0 0;
    font-size: 11px;
    color: #9aa0ab;
}
</style>
