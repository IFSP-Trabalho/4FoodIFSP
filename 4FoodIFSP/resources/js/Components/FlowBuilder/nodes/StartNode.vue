<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({ id: String, data: Object, selected: Boolean });
</script>

<template>
    <div class="sn-wrap" :class="{ 'sn-active': selected }">
        <svg class="sn-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M8 5v14l11-7z" />
        </svg>
        <span class="sn-name">{{ data.name || 'Início' }}</span>

        <Handle type="source" :position="Position.Right" />
    </div>
</template>

<style scoped>
.sn-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 120px;
    min-height: 32px;
    padding: 8px 12px;
    border-radius: 5px;
    border: 1px solid rgba(54, 145, 90, 0.3);
    background: rgba(34, 235, 115, 0.6);
    cursor: default;
}
.sn-active { border-color: rgba(24, 121, 255, 0.5); }
.sn-icon {
    width: 16px;
    height: 16px;
    fill: rgba(54, 145, 90, 1);
    flex-shrink: 0;
}
.sn-name {
    font-size: 13px;
    font-weight: 600;
    color: rgba(36, 110, 70, 1);
    white-space: nowrap;
}
</style>
