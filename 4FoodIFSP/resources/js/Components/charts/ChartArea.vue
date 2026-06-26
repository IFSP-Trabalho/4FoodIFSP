<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    // [{ label: string, value: number, display: string, hint?: string }]
    points: { type: Array, default: () => [] },
    color: { type: String, default: '#993c1d' },
    height: { type: Number, default: 240 },
    valueType: { type: String, default: 'number' }, // 'number' | 'currency'
    emptyText: { type: String, default: 'Sem dados no período.' },
});

const wrap = ref(null);
const svgEl = ref(null);
const width = ref(640);
const hoverIndex = ref(null);
let observer = null;

onMounted(() => {
    if (!wrap.value) {
        return;
    }

    width.value = wrap.value.clientWidth || 640;
    observer = new ResizeObserver((entries) => {
        for (const entry of entries) {
            width.value = Math.max(entry.contentRect.width, 240);
        }
    });
    observer.observe(wrap.value);
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
    }
});

const pad = { top: 18, right: 18, bottom: 30, left: 48 };

const values = computed(() => props.points.map((point) => Number(point.value) || 0));
const innerW = computed(() => Math.max(width.value - pad.left - pad.right, 10));
const innerH = computed(() => props.height - pad.top - pad.bottom);

function niceCeil(value) {
    if (value <= 0) {
        return 1;
    }

    const pow = Math.pow(10, Math.floor(Math.log10(value)));
    const n = value / pow;
    const nice = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
    return nice * pow;
}

const maxV = computed(() => niceCeil(Math.max(0, ...values.value)));

function xFor(index) {
    const n = props.points.length;
    if (n <= 1) {
        return pad.left + innerW.value / 2;
    }

    return pad.left + (index / (n - 1)) * innerW.value;
}

function yFor(value) {
    return pad.top + innerH.value - (maxV.value > 0 ? (value / maxV.value) * innerH.value : 0);
}

const linePath = computed(() =>
    props.points
        .map((point, i) => `${i === 0 ? 'M' : 'L'}${xFor(i).toFixed(1)},${yFor(values.value[i]).toFixed(1)}`)
        .join(' '),
);

const areaPath = computed(() => {
    if (!props.points.length) {
        return '';
    }

    const base = (pad.top + innerH.value).toFixed(1);
    const last = xFor(props.points.length - 1).toFixed(1);
    const first = xFor(0).toFixed(1);
    return `${linePath.value} L${last},${base} L${first},${base} Z`;
});

const yTicks = computed(() => [0, maxV.value / 2, maxV.value]);

function fmt(value) {
    if (props.valueType === 'currency') {
        if (value >= 1000) {
            const short = (value / 1000).toFixed(value % 1000 === 0 ? 0 : 1).replace('.', ',');
            return `R$${short}k`;
        }
        return `R$${Math.round(value)}`;
    }

    return String(Math.round(value));
}

const xStep = computed(() => Math.max(1, Math.ceil(props.points.length / 6)));

function showXLabel(index) {
    return index === props.points.length - 1 || index % xStep.value === 0;
}

function onMove(event) {
    if (!props.points.length || !svgEl.value) {
        return;
    }

    const rect = svgEl.value.getBoundingClientRect();
    const scale = rect.width > 0 ? width.value / rect.width : 1;
    const mx = (event.clientX - rect.left) * scale;

    let best = 0;
    let bestDist = Infinity;
    for (let i = 0; i < props.points.length; i++) {
        const dist = Math.abs(xFor(i) - mx);
        if (dist < bestDist) {
            bestDist = dist;
            best = i;
        }
    }
    hoverIndex.value = best;
}

const active = computed(() => (hoverIndex.value === null ? null : props.points[hoverIndex.value]));

const tipStyle = computed(() => {
    if (hoverIndex.value === null) {
        return {};
    }

    const x = Math.min(Math.max(xFor(hoverIndex.value), 56), width.value - 56);
    return { left: `${x}px`, top: `${yFor(values.value[hoverIndex.value])}px` };
});

const gradientId = `area-grad-${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <div ref="wrap" class="chart-wrap">
        <svg
            v-if="points.length"
            ref="svgEl"
            :width="width"
            :height="height"
            role="img"
            @mousemove="onMove"
            @mouseleave="hoverIndex = null"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.24" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <g class="grid">
                <template v-for="(tick, i) in yTicks" :key="i">
                    <line :x1="pad.left" :x2="width - pad.right" :y1="yFor(tick)" :y2="yFor(tick)" />
                    <text :x="pad.left - 10" :y="yFor(tick) + 4" text-anchor="end">{{ fmt(tick) }}</text>
                </template>
            </g>

            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <text
                v-for="(point, i) in points"
                v-show="showXLabel(i)"
                :key="`x-${i}`"
                class="x-label"
                :x="xFor(i)"
                :y="height - 9"
                text-anchor="middle"
            >
                {{ point.label }}
            </text>

            <g v-if="hoverIndex !== null" class="hover-layer">
                <line
                    :x1="xFor(hoverIndex)"
                    :x2="xFor(hoverIndex)"
                    :y1="pad.top"
                    :y2="pad.top + innerH"
                    class="guide"
                />
                <circle :cx="xFor(hoverIndex)" :cy="yFor(values[hoverIndex])" r="7" :fill="color" opacity="0.18" />
                <circle
                    :cx="xFor(hoverIndex)"
                    :cy="yFor(values[hoverIndex])"
                    r="4.5"
                    :fill="color"
                    stroke="#fff"
                    stroke-width="2"
                />
            </g>
        </svg>
        <p v-else class="chart-empty">{{ emptyText }}</p>

        <transition name="tip">
            <div v-if="active" class="chart-tip" :style="tipStyle">
                <span class="tip-label">{{ active.label }}</span>
                <strong>{{ active.display }}</strong>
                <span v-if="active.hint" class="tip-hint">{{ active.hint }}</span>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.chart-wrap {
    position: relative;
    width: 100%;
    padding: 8px 8px 2px;
}

svg {
    display: block;
}

.grid line {
    stroke: var(--fg-eef0f2);
    stroke-width: 1;
}

.grid text,
.x-label {
    fill: var(--fg-9aa0ab);
    font-size: 10px;
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
}

.guide {
    stroke: var(--fg-c9ccd2);
    stroke-width: 1;
    stroke-dasharray: 4 4;
}

.chart-tip {
    position: absolute;
    z-index: 5;
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 14px));
    background: var(--bg-17181e);
    color: var(--fg-ffffff);
    border-radius: 11px;
    padding: 8px 11px;
    box-shadow: 0 10px 26px rgba(15, 16, 22, 0.22);
    display: grid;
    gap: 1px;
    white-space: nowrap;
}

.chart-tip::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -5px;
    transform: translateX(-50%) rotate(45deg);
    width: 9px;
    height: 9px;
    background: var(--bg-17181e);
    border-radius: 2px;
}

.chart-tip .tip-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--fg-b9bcc4);
}

.chart-tip strong {
    font-size: 14px;
    font-weight: 700;
}

.chart-tip .tip-hint {
    font-size: 11px;
    color: var(--fg-c9ccd2);
}

.tip-enter-active,
.tip-leave-active {
    transition: opacity 0.12s ease;
}

.tip-enter-from,
.tip-leave-to {
    opacity: 0;
}

.chart-empty {
    padding: 30px 12px;
    text-align: center;
    color: var(--fg-9aa0ab);
    font-size: 13px;
}
</style>
