<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const isUserMenuOpen = ref(false);

function toggleUserMenu() {
    isUserMenuOpen.value = !isUserMenuOpen.value;
}

function logout() {
    isUserMenuOpen.value = false;
    router.post('/logout');
}

function onWindowClick(event) {
    if (!(event.target instanceof HTMLElement)) return;
    if (!event.target.closest('.wa-user-wrapper')) {
        isUserMenuOpen.value = false;
    }
}

onMounted(() => window.addEventListener('click', onWindowClick));
onBeforeUnmount(() => window.removeEventListener('click', onWindowClick));
</script>

<template>
    <aside class="wa-sidebar">
        <div class="wa-logo">
            <img src="/img/logo.png" alt="2MP Soft" />
        </div>

        <nav class="wa-nav">
            <button type="button" class="wa-nav-item active" title="Atendimento" aria-label="Atendimento">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.37 5.07L2 22l5.09-1.34A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.2 14.2c-.22.62-1.3 1.18-1.8 1.22-.46.04-.9.2-3.05-.64-2.6-1.03-4.26-3.67-4.39-3.84-.13-.17-1.07-1.42-1.07-2.71 0-1.29.67-1.93.91-2.19.24-.26.52-.33.7-.33l.5.01c.16 0 .38-.06.6.46l.77 1.87c.06.15.1.32.01.5l-.29.52-.44.45c-.13.13-.27.27-.12.53.15.26.68 1.12 1.46 1.81.99.88 1.84 1.16 2.1 1.29.26.13.41.11.56-.07l.72-.85c.19-.22.37-.14.62-.05l1.77.83c.26.12.43.18.49.28.07.1.07.56-.15 1.11Z" />
                </svg>
            </button>
        </nav>

        <div class="wa-bottom">
            <div class="wa-user-wrapper">
                <button
                    type="button"
                    class="wa-avatar"
                    aria-label="Menu do usuário"
                    @click.stop="toggleUserMenu"
                >
                    A
                </button>
                <div v-if="isUserMenuOpen" class="wa-user-menu">
                    <button type="button" class="wa-logout-btn" @click="logout">Sair</button>
                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped src="./styles/WaSidebar.css"></style>
