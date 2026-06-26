<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    path: { type: String, required: true },
    dateFrom: { type: String, required: true },
    dateTo: { type: String, required: true },
});

const from = ref(props.dateFrom);
const to = ref(props.dateTo);
const activePreset = ref(null);

function apply() {
    router.get(
        props.path,
        { date_from: from.value, date_to: to.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onManualChange() {
    activePreset.value = null;
    apply();
}

function setRange(days, key) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days);
    from.value = start.toISOString().slice(0, 10);
    to.value = end.toISOString().slice(0, 10);
    activePreset.value = key;
    apply();
}
</script>

<template>
    <div class="report-filters">
        <div class="report-filters-dates">
            <svg class="cal-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z" />
            </svg>
            <label>
                <span>De</span>
                <input v-model="from" type="date" @change="onManualChange">
            </label>
            <span class="dash">—</span>
            <label>
                <span>Até</span>
                <input v-model="to" type="date" @change="onManualChange">
            </label>
        </div>
        <div class="report-filters-presets">
            <button type="button" :class="{ on: activePreset === 'today' }" @click="setRange(0, 'today')">Hoje</button>
            <button type="button" :class="{ on: activePreset === '7d' }" @click="setRange(6, '7d')">7 dias</button>
            <button type="button" :class="{ on: activePreset === '30d' }" @click="setRange(29, '30d')">30 dias</button>
        </div>
    </div>
</template>

<style scoped>
.report-filters {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    background: var(--bg-ffffff);
    border: 1px solid var(--bd-eef0f2);
    border-radius: 16px;
    padding: 12px 14px;
    box-shadow: 0 1px 2px rgba(16, 17, 22, 0.04);
}

.report-filters-dates {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.cal-icon {
    width: 18px;
    height: 18px;
    fill: var(--fg-993c1d);
    margin-bottom: 8px;
}

.report-filters-dates label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.report-filters-dates span {
    font-family: 'JetBrains Mono', 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 10px;
    color: var(--fg-7f8391);
}

.report-filters-dates .dash {
    color: var(--fg-c4c8cf);
    margin-bottom: 8px;
}

.report-filters-dates input {
    border: 1px solid var(--bd-e1e3e7);
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 13px;
    color: var(--fg-17181e);
    background: var(--bg-ffffff);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.report-filters-dates input:focus {
    outline: none;
    border-color: var(--bd-993c1d);
    box-shadow: 0 0 0 3px rgba(153, 60, 29, 0.12);
}

.report-filters-presets {
    display: inline-flex;
    background: var(--bg-f4f5f7);
    border-radius: 999px;
    padding: 3px;
    gap: 2px;
}

.report-filters-presets button {
    border: 0;
    background: transparent;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 600;
    color: var(--fg-6b7079);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.report-filters-presets button:hover {
    color: var(--fg-993c1d);
}

.report-filters-presets button.on {
    background: var(--bg-ffffff);
    color: var(--fg-993c1d);
    box-shadow: 0 1px 3px rgba(16, 17, 22, 0.12);
}
</style>
