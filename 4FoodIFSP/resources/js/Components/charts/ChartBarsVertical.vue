<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    // [{ label: string, value: number, display: string, pico?: boolean }]
    items:      { type: Array,   default: () => [] },
    color:      { type: String,  default: '#6366F1' },
    peakColor:  { type: String,  default: '#10B981' },
    height:     { type: Number,  default: 140 },
    showLabels: { type: Boolean, default: true },
    emptyText:  { type: String,  default: 'Sem dados.' },
});

const wrap  = ref(null);
const width = ref(640);
let observer = null;

onMounted(() => {
    if (!wrap.value) return;
    width.value = wrap.value.clientWidth || 640;
    observer = new ResizeObserver((entries) => {
        for (const e of entries) width.value = Math.max(e.contentRect.width, 160);
    });
    observer.observe(wrap.value);
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});

const pad = computed(() => ({
    top: 18,
    right: 8,
    bottom: props.showLabels ? 22 : 6,
    left: 28,
}));

const maxV   = computed(() => Math.max(1, ...props.items.map((i) => Number(i.value) || 0)));
const innerW = computed(() => Math.max(width.value - pad.value.left - pad.value.right, 10));
const innerH = computed(() => Math.max(props.height - pad.value.top - pad.value.bottom, 10));

const slotW = computed(() =>
    props.items.length > 0 ? innerW.value / props.items.length : 20,
);
const barW = computed(() => Math.max(3, Math.min(32, slotW.value - 4)));

function barX(i) {
    return pad.value.left + i * slotW.value + (slotW.value - barW.value) / 2;
}

function bH(value) {
    return Math.max(2, (Number(value) || 0) / maxV.value * innerH.value);
}

function bY(value) {
    return pad.value.top + innerH.value - bH(value);
}

function showXLabel(i) {
    if (!props.showLabels) return false;
    if (props.items.length <= 12) return true;
    return i % 4 === 0 || i === props.items.length - 1;
}

const yTicks = computed(() => {
    const top = maxV.value;
    const mid = Math.round(top / 2);
    return [0, mid, top].filter((v, i, a) => a.indexOf(v) === i);
});

function yPos(v) {
    return pad.value.top + innerH.value - (v / maxV.value) * innerH.value;
}
</script>

<template>
    <div ref="wrap" class="vbars-wrap">
        <svg
            v-if="items.length"
            :width="width"
            :height="height"
            role="img"
            aria-label="Gráfico de barras verticais"
        >
            <!-- Y grid -->
            <g class="v-grid">
                <template v-for="tick in yTicks" :key="tick">
                    <line :x1="pad.left" :x2="width - pad.right" :y1="yPos(tick)" :y2="yPos(tick)" />
                    <text :x="pad.left - 4" :y="yPos(tick) + 4" text-anchor="end" class="axis-text">
                        {{ tick }}
                    </text>
                </template>
            </g>

            <!-- Bars -->
            <rect
                v-for="(item, i) in items"
                :key="i"
                :x="barX(i)"
                :y="bY(item.value)"
                :width="barW"
                :height="bH(item.value)"
                :fill="item.pico ? peakColor : color"
                :opacity="item.pico ? 1 : 0.75"
                rx="3"
            />

            <!-- Peak value label -->
            <template v-for="(item, i) in items" :key="`pl-${i}`">
                <text
                    v-if="item.pico && Number(item.value) > 0"
                    :x="barX(i) + barW / 2"
                    :y="bY(item.value) - 5"
                    text-anchor="middle"
                    class="peak-label"
                >
                    {{ item.display }}
                </text>
            </template>

            <!-- X labels -->
            <text
                v-for="(item, i) in items"
                v-show="showXLabel(i)"
                :key="`xl-${i}`"
                :x="barX(i) + barW / 2"
                :y="height - 5"
                text-anchor="middle"
                class="axis-text"
            >
                {{ item.label }}
            </text>
        </svg>
        <p v-else class="chart-empty">{{ emptyText }}</p>
    </div>
</template>

<style scoped>
.vbars-wrap {
    position: relative;
    width: 100%;
    padding: 8px 8px 2px;
}

svg {
    display: block;
}

.v-grid line {
    stroke: var(--fg-eef0f2);
    stroke-width: 1;
}

.axis-text {
    fill: var(--fg-9aa0ab);
    font-size: 9px;
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
}

.peak-label {
    fill: var(--fg-1d9e75);
    font-size: 10px;
    font-weight: 700;
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
}

.chart-empty {
    padding: 24px 12px;
    text-align: center;
    color: var(--fg-9aa0ab);
    font-size: 13px;
}
</style>
