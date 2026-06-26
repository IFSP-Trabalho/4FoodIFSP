<script setup>
defineProps({ id: String, data: Object, selected: Boolean });
</script>

<template>
    <div class="cn-wrap" :class="{ 'cn-active': selected }">
        <div class="cn-header">
            <svg class="cn-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" />
            </svg>
            <span class="cn-name">Configurações</span>
        </div>
    </div>
</template>

<style scoped>
.cn-wrap {
    min-width: 160px;
    border-radius: 5px;
    background: var(--bg-ffffff);
    border: 1px solid var(--bd-e0e3e7);
    opacity: 0.9;
    cursor: default;
}
.cn-wrap:hover  { border: 1px dashed var(--bd-1879ff); background: var(--bg-f0f7ff); }
.cn-active      { opacity: 1; border-color: rgba(24, 121, 255, 0.5); }
.cn-header      { display: flex; align-items: center; gap: 6px; padding: 6px 10px; }
.cn-icon        { width: 16px; height: 16px; fill: rgba(0, 0, 0, 0.35); flex-shrink: 0; }
.cn-name        { font-size: 13px; font-weight: 600; color: var(--fg-17181e); }
</style>
