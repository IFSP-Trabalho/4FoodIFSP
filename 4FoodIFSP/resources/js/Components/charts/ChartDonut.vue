<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // [{ label: string, value: number, display: string }]
    items: { type: Array, default: () => [] },
    palette: {
        type: Array,
        default: () => ['#993c1d', '#b85a34', '#cf7d52', '#e0a075', '#ecc3a0', '#1d9e75', '#378add', '#cbb8ad'],
    },
    size: { type: Number, default: 176 },
    thickness: { type: Number, default: 26 },
    centerLabel: { type: String, default: '' },
    emptyText: { type: String, default: 'Sem dados no período.' },
});

const wrap = ref(null);
const hoverIndex = ref(null);
const tip = ref({ x: 0, y: 0 });

const total = computed(() => props.items.reduce((sum, item) => sum + (Number(item.value) || 0), 0));
const radius = computed(() => (props.size - props.thickness - 8) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const center = computed(() => props.size / 2);

const slices = computed(() => {
    let acc = 0;
    return props.items.map((item, index) => {
        const value = Number(item.value) || 0;
        const frac = total.value > 0 ? value / total.value : 0;
        const dash = frac * circumference.value;
        const slice = {
            color: props.palette[index % props.palette.length],
            dash,
            gap: circumference.value - dash,
            offset: -acc * circumference.value,
            label: item.label,
            display: item.display,
            pct: total.value > 0 ? Math.round(frac * 100) : 0,
        };
        acc += frac;
        return slice;
    });
});

function onMove(event) {
    if (!wrap.value) {
        return;
    }

    const rect = wrap.value.getBoundingClientRect();
    tip.value = { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

const active = computed(() => (hoverIndex.value === null ? null : slices.value[hoverIndex.value]));

const tipStyle = computed(() => {
    if (!wrap.value) {
        return {};
    }

    const w = wrap.value.clientWidth || 0;
    const x = Math.min(Math.max(tip.value.x, 70), w - 70);
    return { left: `${x}px`, top: `${tip.value.y}px` };
});
</script>

<template>
    <div ref="wrap" class="donut-wrap" @mousemove="onMove" @mouseleave="hoverIndex = null">
        <div v-if="items.length && total > 0" class="donut">
            <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" role="img">
                <g :transform="`rotate(-90 ${center} ${center})`">
                    <circle :cx="center" :cy="center" :r="radius" fill="none" stroke="#f0f1f3" :stroke-width="thickness" />
                    <circle
                        v-for="(slice, i) in slices"
                        :key="i"
                        :cx="center"
                        :cy="center"
                        :r="radius"
                        fill="none"
                        :stroke="slice.color"
                        :stroke-width="hoverIndex === i ? thickness + 6 : thickness"
                        :stroke-dasharray="`${slice.dash} ${slice.gap}`"
                        :stroke-dashoffset="slice.offset"
                        stroke-linecap="butt"
                        class="slice"
                        :class="{ dim: hoverIndex !== null && hoverIndex !== i }"
                        @mouseenter="hoverIndex = i"
                    />
                </g>
                <text :x="center" :y="center - 3" text-anchor="middle" class="donut-total">
                    {{ active ? active.display : total }}
                </text>
                <text :x="center" :y="center + 15" text-anchor="middle" class="donut-cap">
                    {{ active ? active.label : centerLabel }}
                </text>
            </svg>
            <ul class="legend">
                <li
                    v-for="(slice, i) in slices"
                    :key="i"
                    :class="{ on: hoverIndex === i }"
                    @mouseenter="hoverIndex = i"
                >
                    <span class="dot" :style="{ background: slice.color }" />
                    <span class="legend-label">{{ slice.label }}</span>
                    <span class="legend-val">{{ slice.display }} · {{ slice.pct }}%</span>
                </li>
            </ul>
        </div>
        <p v-else class="chart-empty">{{ emptyText }}</p>

        <transition name="tip">
            <div v-if="active" class="chart-tip" :style="tipStyle">
                <span class="tip-label">{{ active.label }}</span>
                <strong>{{ active.display }}</strong>
                <span class="tip-hint">{{ active.pct }}% do total</span>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.donut-wrap {
    position: relative;
}

.donut {
    display: flex;
    gap: 18px;
    align-items: center;
    padding: 16px 14px;
    flex-wrap: wrap;
}

.slice {
    transition: stroke-width 0.15s ease, opacity 0.15s ease;
    cursor: default;
}

.slice.dim {
    opacity: 0.35;
}

.donut-total {
    fill: #17181e;
    font-size: 20px;
    font-weight: 700;
}

.donut-cap {
    fill: #9aa0ab;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.legend {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 4px;
    flex: 1;
    min-width: 170px;
}

.legend li {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13px;
    color: #2b2d35;
    padding: 5px 7px;
    border-radius: 8px;
    transition: background 0.15s ease;
}

.legend li.on {
    background: #faf6f4;
}

.dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
}

.legend-label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.legend-val {
    color: #555a66;
    font-weight: 600;
    white-space: nowrap;
}

.chart-tip {
    position: absolute;
    z-index: 5;
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 14px));
    background: #17181e;
    color: #fff;
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
    background: #17181e;
    border-radius: 2px;
}

.chart-tip .tip-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #b9bcc4;
}

.chart-tip strong {
    font-size: 14px;
    font-weight: 700;
}

.chart-tip .tip-hint {
    font-size: 11px;
    color: #c9ccd2;
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
    color: #9aa0ab;
    font-size: 13px;
}
</style>
