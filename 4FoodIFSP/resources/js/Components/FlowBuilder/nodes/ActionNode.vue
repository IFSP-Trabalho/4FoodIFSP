<script setup>
import { Handle, Position } from '@vue-flow/core';

const ACTION_LABELS = { handoff: 'Transferir atendente', end: 'Encerrar' };

defineProps({ id: String, data: Object, selected: Boolean });
</script>

<template>
    <div class="an-wrap" :class="{ 'an-active': selected }">
        <Handle type="target" :position="Position.Left" />

        <div class="an-header">
            <span class="an-icon">⚡</span>
            <span class="an-name">{{ data.name || 'Ação' }}</span>
        </div>

        <div v-if="data.action" class="an-body">
            <span class="an-badge">{{ ACTION_LABELS[data.action] ?? data.action }}</span>
        </div>

        <Handle type="source" :position="Position.Right" />
    </div>
</template>

<style scoped>
.an-wrap    { min-width: 170px; max-width: 370px; min-height: 32px; border-radius: 5px; background: var(--bg-ffffff); border: 1px solid var(--bd-e0e3e7); opacity: 0.9; cursor: move; }
.an-wrap:hover  { border: 1px dashed var(--bd-1879ff); background: var(--bg-f0f7ff); }
.an-active  { opacity: 1; border-color: rgba(24,121,255,.5); }
.an-header  { display: flex; align-items: center; gap: 6px; padding: 6px 10px; }
.an-icon    { font-size: 14px; color: rgba(0,0,0,.3); }
.an-name    { font-size: 13px; font-weight: 600; color: var(--fg-17181e); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.an-body    { padding: 4px 10px 8px; border-top: 1px solid var(--bd-f0f1f3); }
.an-badge   { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--bg-fdf0e9); color: var(--fg-993c1d); }
</style>
