<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // [{ label: string, value: number, display: string, sub?: string }]
    items: { type: Array, default: () => [] },
    color: { type: String, default: '#993c1d' },
    rank: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Sem dados no período.' },
});

const wrap = ref(null);
const hoverIndex = ref(null);
const tip = ref({ x: 0, y: 0 });

const max = computed(() => Math.max(0, ...props.items.map((item) => Number(item.value) || 0)));

function widthFor(value) {
    if (max.value <= 0) {
        return '0%';
    }

    return `${Math.max((Number(value) || 0) / max.value * 100, 3)}%`;
}

function onEnter(index) {
    hoverIndex.value = index;
}

function onMove(event) {
    if (!wrap.value) {
        return;
    }

    const rect = wrap.value.getBoundingClientRect();
    tip.value = { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

const active = computed(() => (hoverIndex.value === null ? null : props.items[hoverIndex.value]));

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
    <div ref="wrap" class="bars-wrap" @mouseleave="hoverIndex = null">
        <div v-if="items.length" class="bars">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="bar-row"
                :class="{ dim: hoverIndex !== null && hoverIndex !== index }"
                @mouseenter="onEnter(index)"
                @mousemove="onMove"
            >
                <span v-if="rank" class="bar-rank">{{ index + 1 }}</span>
                <div class="bar-main">
                    <div class="bar-top">
                        <span class="bar-label">
                            {{ item.label }}
                            <small v-if="item.sub">{{ item.sub }}</small>
                        </span>
                        <span class="bar-value">{{ item.display }}</span>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" :style="{ width: widthFor(item.value), background: color }" />
                    </div>
                </div>
            </div>
        </div>
        <p v-else class="chart-empty">{{ emptyText }}</p>

        <transition name="tip">
            <div v-if="active" class="chart-tip" :style="tipStyle">
                <span class="tip-label">{{ active.label }}</span>
                <strong>{{ active.display }}</strong>
                <span v-if="active.sub" class="tip-hint">{{ active.sub }}</span>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.bars-wrap {
    position: relative;
}

.bars {
    display: grid;
    gap: 6px;
    padding: 12px 10px;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 8px;
    border-radius: 10px;
    transition: background 0.15s ease, opacity 0.15s ease;
}

.bar-row:hover {
    background: #faf6f4;
}

.bar-row.dim {
    opacity: 0.5;
}

.bar-rank {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 7px;
    background: #f3eae5;
    color: #993c1d;
    font-size: 11px;
    font-weight: 700;
}

.bar-main {
    flex: 1;
    min-width: 0;
    display: grid;
    gap: 6px;
}

.bar-top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    color: #2b2d35;
}

.bar-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bar-label small {
    color: #9aa0ab;
    font-size: 11px;
    margin-left: 6px;
}

.bar-value {
    font-weight: 700;
    color: #17181e;
    white-space: nowrap;
}

.bar-track {
    height: 9px;
    border-radius: 999px;
    background: #f0f1f3;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1);
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
