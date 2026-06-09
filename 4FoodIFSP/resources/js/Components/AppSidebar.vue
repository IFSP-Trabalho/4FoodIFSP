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

// Tela inicial (ícone Home) de cada departamento.
const homeRouteByRole = {
    admin: '/admin/dashboard',
    kitchen: '/admin/orders',
    finance: '/admin/orders',
    waiter: '/admin/mesas',
    whatsapp_agent: '/whatsapp/inbox',
};

const reportsByRole = {
    admin: ['vendas', 'cozinha', 'garcom', 'whatsapp'],
    finance: ['vendas'],
    kitchen: ['cozinha'],
    waiter: ['garcom'],
};

const reportsOptions = [
    { key: 'vendas', label: 'Vendas', icon: 'finance', route: '/relatorios/vendas' },
    { key: 'cozinha', label: 'Cozinha', icon: 'dishes', route: '/relatorios/cozinha' },
    { key: 'garcom', label: 'Garçom', icon: 'tables', route: '/relatorios/garcom' },
    { key: 'whatsapp', label: 'WhatsApp', icon: 'whatsapp', route: '/relatorios/whatsapp' },
].filter((option) => (reportsByRole[userRole] ?? []).includes(option.key));

const items = [
    { key: 'home', label: 'Home', icon: 'home', route: homeRouteByRole[userRole] ?? '/admin/dashboard' },
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', route: null, roles: ['admin'] },
    { key: 'orders', label: 'Orders', icon: 'orders', route: '/admin/orders', roles: ['admin', 'kitchen', 'finance', 'waiter'] },
    { key: 'tables', label: 'Mesas', icon: 'tables', route: '/admin/mesas', roles: ['admin', 'finance', 'waiter'] },
    { key: 'cadastros', label: 'Cadastros', icon: 'cadastros', route: null, roles: ['admin'] },
    { key: 'finance', label: 'Financeiro', icon: 'finance', route: null, roles: ['admin'] },
    { key: 'reports', label: 'Relatorios', icon: 'reports', route: null },
    { key: 'whatsapp-conexoes', label: 'Conexoes', icon: 'whatsapp-conexoes', route: '/admin/whatsapp/conexoes', roles: ['admin'] },
    { key: 'whatsapp', label: 'Atendimento', icon: 'whatsapp', route: '/whatsapp/inbox', roles: ['admin', 'whatsapp_agent'] },
    { key: 'whatsapp-contatos', label: 'Contatos', icon: 'whatsapp-contatos', route: '/whatsapp/contatos', roles: ['admin', 'whatsapp_agent'] },
    { key: 'chatbot', label: 'ChatBot', icon: 'robot', route: null, roles: ['admin'] },
].filter((item) => {
    if (item.roles && !item.roles.includes(userRole)) {
        return false;
    }

    if (item.key === 'reports' && reportsOptions.length === 0) {
        return false;
    }

    return true;
});

const cadastrosOptions = [
    { key: 'users', label: 'Usuarios', icon: 'users', route: '/admin/cadastros/users' },
    { key: 'departments', label: 'Departamentos', icon: 'departments', route: '/admin/cadastros/departments' },
    { key: 'dishes', label: 'Pratos', icon: 'dishes', route: '/admin/cadastros/dishes' },
    { key: 'wa-motivos', label: 'Motivos WA', icon: 'wa-motivos', route: '/admin/cadastros/wa-motivos' },
];

const chatbotOptions = [
    { key: 'chatbot', label: 'ChatBot', icon: 'robot', route: '/admin/chatbot' },
];

const splitAfter = new Set(['dashboard', 'cadastros']);
const isCadastrosMenuOpen = ref(false);
const isReportsMenuOpen = ref(false);
const isChatbotMenuOpen = ref(false);
const isUserMenuOpen = ref(false);

function isDisabled(item) {
    return !item.route && item.key !== 'cadastros' && item.key !== 'reports' && item.key !== 'chatbot';
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
        isReportsMenuOpen.value = false;
        isChatbotMenuOpen.value = false;
        isCadastrosMenuOpen.value = !isCadastrosMenuOpen.value;
        return;
    }

    if (item.key === 'reports') {
        isCadastrosMenuOpen.value = false;
        isChatbotMenuOpen.value = false;
        isReportsMenuOpen.value = !isReportsMenuOpen.value;
        return;
    }

    if (item.key === 'chatbot') {
        isCadastrosMenuOpen.value = false;
        isReportsMenuOpen.value = false;
        isChatbotMenuOpen.value = !isChatbotMenuOpen.value;
        return;
    }

    if (isDisabled(item)) {
        return;
    }

    isCadastrosMenuOpen.value = false;
    isReportsMenuOpen.value = false;
    isChatbotMenuOpen.value = false;
    router.visit(item.route);
}

function onCadastrosOptionSelect(route) {
    isCadastrosMenuOpen.value = false;
    router.visit(route);
}

function onReportsOptionSelect(route) {
    isReportsMenuOpen.value = false;
    router.visit(route);
}

function onChatbotOptionSelect(route) {
    isChatbotMenuOpen.value = false;
    router.visit(route);
}

function toggleUserMenu() {
    isUserMenuOpen.value = !isUserMenuOpen.value;
    isCadastrosMenuOpen.value = false;
    isReportsMenuOpen.value = false;
    isChatbotMenuOpen.value = false;
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

    if (!event.target.closest('.reports-wrapper')) {
        isReportsMenuOpen.value = false;
    }

    if (!event.target.closest('.chatbot-wrapper')) {
        isChatbotMenuOpen.value = false;
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
                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
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
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                        </svg>
                        <svg v-else-if="option.icon === 'departments'" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2l-5.5 9h11zm0 3.84L13.93 9h-3.87zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5S15.01 22 17.5 22s4.5-2.01 4.5-4.5S19.99 13 17.5 13zm0 7c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5zM11 13.5H3v8h8v-8zm-2 6H5v-4h4v4z" />
                        </svg>
                        <svg v-else-if="option.icon === 'dishes'" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z" />
                        </svg>
                        <svg v-else-if="option.icon === 'wa-motivos'" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 9h-2V5h2v6zm0 4h-2v-2h2v2z" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h10v2H4z" />
                        </svg>
                        {{ option.label }}
                    </button>
                </div>
            </div>
            <div v-else-if="item.key === 'reports'" class="reports-wrapper cadastros-wrapper">
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
                        <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1H5V5h14v14.1zm0-16.1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                    </svg>
                </button>

                <div v-if="isReportsMenuOpen" class="cadastros-menu">
                    <button
                        v-for="option in reportsOptions"
                        :key="option.key"
                        type="button"
                        class="cadastros-menu-item"
                        @click="onReportsOptionSelect(option.route)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1H5V5h14v14.1zm0-16.1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                        </svg>
                        {{ option.label }}
                    </button>
                </div>
            </div>
            <div v-else-if="item.key === 'chatbot'" class="chatbot-wrapper cadastros-wrapper">
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
                        <path d="M4 8h4V4H4v4zm6 12h4v-4h-4v4zm-6 0h4v-4H4v4zm0-6h4v-4H4v4zm6 0h4v-4h-4v4zm6-10v4h4V4h-4zm-6 4h4V4h-4v4zm6 6h4v-4h-4v4zm0 6h4v-4h-4v4z" />
                    </svg>
                </button>

                <div v-if="isChatbotMenuOpen" class="cadastros-menu">
                    <button
                        v-for="option in chatbotOptions"
                        :key="option.key"
                        type="button"
                        class="cadastros-menu-item"
                        @click="onChatbotOptionSelect(option.route)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5C7.5 10.67 8.17 10 9 10s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-1-4c-.83 0-1.5-.67-1.5-1.5S14.17 10 15 10s1.5.67 1.5 1.5S15.83 13 15 13z" />
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
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
                </svg>
                <svg v-else-if="item.icon === 'dashboard'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" />
                </svg>
                <svg v-else-if="item.icon === 'orders'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 17H6v-2h12v2zm0-4H6v-2h12v2zm0-4H6V7h12v2zM3 22l1.5-1.5L6 22l1.5-1.5L9 22l1.5-1.5L12 22l1.5-1.5L15 22l1.5-1.5L18 22l1.5-1.5L21 22V2l-1.5 1.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2 4.5 3.5 3 2v20z" />
                </svg>
                <svg v-else-if="item.icon === 'tables'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 3H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 2v3H5V5h15zM5 10h4v9H5v-9zm6 0h4v9h-4v-9zm6 0h3v9h-3v-9z" />
                </svg>
                <svg v-else-if="item.icon === 'finance'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm14-12v7h3v-7h-3zM11.5 1 2 6v2h19V6z" />
                </svg>
                <svg v-else-if="item.icon === 'reports'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1H5V5h14v14.1zm0-16.1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                </svg>
                <svg v-else-if="item.icon === 'whatsapp-conexoes'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17 7H13V9H17C18.65 9 20 10.35 20 12C20 13.65 18.65 15 17 15H13V17H17C19.76 17 22 14.76 22 12C22 9.24 19.76 7 17 7ZM11 15H7C5.35 15 4 13.65 4 12C4 10.35 5.35 9 7 9H11V7H7C4.24 7 2 9.24 2 12C2 14.76 4.24 17 7 17H11V15ZM8 11H16V13H8V11Z" />
                </svg>
                <svg v-else-if="item.icon === 'whatsapp'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.37 5.07L2 22l5.09-1.34A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.2 14.2c-.22.62-1.3 1.18-1.8 1.22-.46.04-.9.2-3.05-.64-2.6-1.03-4.26-3.67-4.39-3.84-.13-.17-1.07-1.42-1.07-2.71 0-1.29.67-1.93.91-2.19.24-.26.52-.33.7-.33l.5.01c.16 0 .38-.06.6.46l.77 1.87c.06.15.1.32.01.5l-.29.52-.44.45c-.13.13-.27.27-.12.53.15.26.68 1.12 1.46 1.81.99.88 1.84 1.16 2.1 1.29.26.13.41.11.56-.07l.72-.85c.19-.22.37-.14.62-.05l1.77.83c.26.12.43.18.49.28.07.1.07.56-.15 1.11Z" />
                </svg>
                <svg v-else-if="item.icon === 'whatsapp-contatos'" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
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
