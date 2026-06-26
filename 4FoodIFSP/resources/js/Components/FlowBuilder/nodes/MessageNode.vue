<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';

const props = defineProps({ id: String, data: Object, selected: Boolean });

const preview = computed(() => {
    const list = props.data?.interactions;
    if (Array.isArray(list) && list.length) {
        return list.map(i => i.text).filter(Boolean).join('  ');
    }
    return props.data?.text || '';
});
</script>

<template>
    <div class="mn-wrap" :class="{ 'mn-active': selected }">
        <Handle type="target" :position="Position.Left" />

        <div class="mn-header">
            <span class="mn-name">{{ data.name || 'Mensagem' }}</span>
        </div>

        <div v-if="preview" class="mn-body">
            <p class="mn-text">{{ preview }}</p>
        </div>

        <Handle type="source" :position="Position.Right" />
    </div>
</template>

<style scoped>
.mn-wrap {
    min-width: 170px;
    max-width: 370px;
    min-height: 32px;
    border-radius: 5px;
    background: var(--bg-ffffff);
    border: 1px solid var(--bd-e0e3e7);
    opacity: 0.9;
    cursor: move;
}
.mn-wrap:hover  { border: 1px dashed var(--bd-1879ff); background: var(--bg-f0f7ff); }
.mn-active      { opacity: 1; border-color: rgba(24, 121, 255, 0.5); }
.mn-header      { display: flex; align-items: center; gap: 6px; padding: 6px 10px; }
.mn-icon        { font-size: 14px; color: rgba(0,0,0,.3); }
.mn-name        { font-size: 13px; font-weight: 600; color: var(--fg-17181e); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mn-body        { padding: 4px 10px 8px; border-top: 1px solid var(--bd-f0f1f3); }
.mn-text        { margin: 0; font-size: 12px; line-height: 16px; color: var(--fg-5f6572); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; }
</style>
