<script setup>
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    active: {
        type: String,
        default: 'dashboard',
    },
});

const page = usePage();
const userRole = page.props.auth?.user?.role ?? '';

const items = [
    { key: 'home', label: 'Home', icon: 'home', route: '/admin/dashboard' },
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', route: null },
    { key: 'orders', label: 'Orders', icon: 'orders', route: '/admin/orders' },
    { key: 'tables', label: 'Mesas', icon: 'tables', route: null },
    { key: 'cadastros', label: 'Cadastros', icon: 'cadastros', route: null },
    { key: 'finance', label: 'Financeiro', icon: 'finance', route: null },
    { key: 'reports', label: 'Relatorios', icon: 'reports', route: null },
    { key: 'whatsapp-conexoes', label: 'Conexoes', icon: 'whatsapp-conexoes', route: '/admin/whatsapp/conexoes', adminOnly: true },
    { key: 'whatsapp', label: 'Atendimento', icon: 'whatsapp', route: '/whatsapp/inbox' },
].filter((item) => !item.adminOnly || userRole === 'admin');

const cadastrosOptions = [
    { key: 'users', label: 'Usuarios', icon: 'users', route: '/admin/cadastros/users' },
    { key: 'departments', label: 'Departamentos', icon: 'departments', route: '/admin/cadastros/departments' },
    { key: 'dishes', label: 'Pratos', icon: 'dishes', route: '/admin/cadastros/dishes' },
];

const splitAfter = new Set(['dashboard', 'cadastros']);
const isCadastrosMenuOpen = ref(false);
const isUserMenuOpen = ref(false);

function isDisabled(item) {
    return !item.route && item.key !== 'cadastros';
}

function isActive(item) {
    if (props.active === 'dashboard') {
        return item.key === 'home';
    }

    return item.key === props.active;
}

function onItemClick(item) {
    isUserMenuOpen.value = false;

    if (item.key === 'cadastros') {
        isCadastrosMenuOpen.value = !isCadastrosMenuOpen.value;
        return;
    }

    if (isDisabled(item)) {
        return;
    }

    isCadastrosMenuOpen.value = false;
    router.visit(item.route);
}

function onCadastrosOptionSelect(route) {
    isCadastrosMenuOpen.value = false;
    router.visit(route);
}

function toggleUserMenu() {
    isUserMenuOpen.value = !isUserMenuOpen.value;
    isCadastrosMenuOpen.value = false;
}

function logout() {
    isUserMenuOpen.value = false;
    router.post('/logout');
}

function onWindowClick(event) {
    if (!(event.target instanceof HTMLElement)) {
        return;
    }

    if (!event.target.closest('.cadastros-wrapper')) {
        isCadastrosMenuOpen.value = false;
    }

    if (!event.target.closest('.user-menu-wrapper')) {
        isUserMenuOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('click', onWindowClick);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', onWindowClick);
});
</script>

<template>
    <aside class="sidebar">
        <div class="user-menu-wrapper">
            <button type="button" class="avatar" aria-label="Menu do usuário" @click.stop="toggleUserMenu">
                A
            </button>

            <div v-if="isUserMenuOpen" class="user-menu">
                <button type="button" class="user-menu-item logout" @click="logout">Sair</button>
                <p class="user-menu-version">versão 1.0.0</p>
            </div>
        </div>

        <template v-for="item in items" :key="item.key">
            <div v-if="item.key === 'cadastros'" class="cadastros-wrapper">
                <button
                    type="button"
                    class="nav-item"
                    :class="{
                        active: isActive(item),
                        disabled: isDisabled(item),
                    }"
                    :title="item.label"
                    :aria-label="item.label"
                    @click.stop="onItemClick(item)"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.75 5.5A2.75 2.75 0 0 1 7.5 2.75h8.05A3.7 3.7 0 0 1 19.25 6.45V19a.75.75 0 0 1-.75.75H7.5A2.75 2.75 0 0 1 4.75 17V5.5Zm2.75-1.25A1.25 1.25 0 0 0 6.25 5.5v11.5c0 .69.56 1.25 1.25 1.25h.95V6.45a3.7 3.7 0 0 1 1.1-2.2H7.5Zm2.45 14h7.8V6.45a2.2 2.2 0 0 0-2.2-2.2h-3.4a2.2 2.2 0 0 0-2.2 2.2v11.8ZM13 8.75a.75.75 0 0 1 .75.75v1.5h1.5a.75.75 0 1 1 0 1.5h-1.5V14a.75.75 0 0 1-1.5 0v-1.5h-1.5a.75.75 0 0 1 0-1.5h1.5V9.5a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                </button>

                <div v-if="isCadastrosMenuOpen" class="cadastros-menu">
                    <button
                        v-for="option in cadastrosOptions"
                        :key="option.key"
                        type="button"
                        class="cadastros-menu-item"
                        @click="onCadastrosOptionSelect(option.route)"
                    >
                        <svg v-if="option.icon === 'users'" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16 11a4 4 0 1 0-3.999-4A4 4 0 0 0 16 11Zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm8 1c-2.76 0-8 1.39-8 4.15V20h16v-2.85C24 14.39 18.76 13 16 13Zm-8 1c-.38 0-.8.02-1.24.07C4.61 14.35 2 15.23 2 17.15V20h4v-2.85c0-1.14.47-2.17 1.26-2.99A8.55 8.55 0 0 1 8 14Z" />
                        </svg>
                        <svg v-else-if="option.icon === 'departments'" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 3H4v6h6V3Zm10 0h-6v6h6V3ZM10 15H4v6h6v-6Zm2-4h-2v2h-2v2h2v2h2v-2h2v-2h-2v-2Zm8 4h-6v6h6v-6Z" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8.1 13.34 3.91 9.16a4.01 4.01 0 0 1 5.67-5.67l2.84 2.83-1.41 1.42-2.84-2.84a2 2 0 1 0-2.83 2.83l4.19 4.19-1.43 1.42Zm7.8-7.78 4.19 4.19a4.01 4.01 0 0 1-5.67 5.67l-2.84-2.83 1.41-1.42 2.84 2.84a2 2 0 1 0 2.83-2.83l-4.19-4.19 1.43-1.43ZM8 21l8.94-8.94-1.41-1.41L6.59 19.59 8 21Z" />
                        </svg>
                        {{ option.label }}
                    </button>
                </div>
            </div>
            <button
                v-else
                type="button"
                class="nav-item"
                :class="{
                    active: isActive(item),
                    disabled: isDisabled(item),
                }"
                :title="item.label"
                :aria-label="item.label"
                @click="onItemClick(item)"
            >
                <svg v-if="item.icon === 'home'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 10.5 12 3l9 7.5v9.5a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1z" />
                </svg>
                <svg v-else-if="item.icon === 'dashboard'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 4h7v7H4zm9 0h7v4h-7zM4 13h4v7H4zm6 0h10v7H10z" />
                </svg>
                <svg v-else-if="item.icon === 'orders'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 4h10l2 4v12H5V8zm2 0v4h6V4" />
                </svg>
                <svg v-else-if="item.icon === 'tables'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5h16v3H4zm7 3h2v11h-2zM6 19h12v2H6z" />
                </svg>
                <svg v-else-if="item.icon === 'finance'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 17h16v3H4zM6 9h3v8H6zm5-4h3v12h-3zm5 6h3v6h-3z" />
                </svg>
                <svg v-else-if="item.icon === 'whatsapp-conexoes'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17 7H13V9H17C18.65 9 20 10.35 20 12C20 13.65 18.65 15 17 15H13V17H17C19.76 17 22 14.76 22 12C22 9.24 19.76 7 17 7ZM11 15H7C5.35 15 4 13.65 4 12C4 10.35 5.35 9 7 9H11V7H7C4.24 7 2 9.24 2 12C2 14.76 4.24 17 7 17H11V15ZM8 11H16V13H8V11Z" />
                </svg>
                <svg v-else-if="item.icon === 'whatsapp'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.37 5.07L2 22l5.09-1.34A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.2 14.2c-.22.62-1.3 1.18-1.8 1.22-.46.04-.9.2-3.05-.64-2.6-1.03-4.26-3.67-4.39-3.84-.13-.17-1.07-1.42-1.07-2.71 0-1.29.67-1.93.91-2.19.24-.26.52-.33.7-.33l.5.01c.16 0 .38-.06.6.46l.77 1.87c.06.15.1.32.01.5l-.29.52-.44.45c-.13.13-.27.27-.12.53.15.26.68 1.12 1.46 1.81.99.88 1.84 1.16 2.1 1.29.26.13.41.11.56-.07l.72-.85c.19-.22.37-.14.62-.05l1.77.83c.26.12.43.18.49.28.07.1.07.56-.15 1.11Z" />
                </svg>
                <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h10v2H4z" />
                </svg>
            </button>
            <hr v-if="splitAfter.has(item.key)" class="divider">
        </template>
    </aside>
</template>

<style scoped src="./styles/AppSidebar.css"></style>
