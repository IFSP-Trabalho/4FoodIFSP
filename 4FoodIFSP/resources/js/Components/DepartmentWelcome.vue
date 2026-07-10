<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const role = computed(() => user.value.role ?? '');

const roleLabels = {
    admin: 'Administração',
    kitchen: 'Cozinha',
    finance: 'Financeiro',
    waiter: 'Garçom',
    whatsapp_agent: 'Atendimento',
};

const firstName = computed(() => {
    const name = (user.value.name ?? '').trim();
    return name ? name.split(/\s+/)[0] : 'Bem-vindo';
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Bom dia';
    if (h < 18) return 'Boa tarde';
    return 'Boa noite';
});

const today = computed(() =>
    new Date().toLocaleDateString('pt-BR', {
        weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
    }),
);

const actionsByRole = {
    kitchen: [
        { label: 'Pedidos', desc: 'Acompanhe e prepare os pedidos no quadro', route: '/admin/orders', icon: 'orders' },
        { label: 'Relatório da cozinha', desc: 'Produção, tempos e pratos mais pedidos', route: '/relatorios/cozinha', icon: 'reports' },
    ],
    finance: [
        { label: 'Pedidos', desc: 'Acompanhe os pedidos e pagamentos', route: '/admin/orders', icon: 'orders' },
        { label: 'Relatório de vendas', desc: 'Faturamento, recebido e ticket médio', route: '/relatorios/vendas', icon: 'reports' },
    ],
    waiter: [
        { label: 'Mesas', desc: 'Gerencie as mesas e contas do salão', route: '/admin/mesas', icon: 'tables' },
        { label: 'Relatório do garçom', desc: 'Mesas atendidas e contas fechadas', route: '/relatorios/garcom', icon: 'reports' },
    ],
};

const actions = computed(() => actionsByRole[role.value] ?? []);

function go(route) {
    router.visit(route);
}
</script>

<template>
    <div class="shell">
        <AppSidebar />
        <div class="main">
            <AppTopbar title="Início" :subtitle="roleLabels[role] ?? 'Departamento'" />

            <div class="content">
                <section class="welcome-hero">
                    <img :src="'/img/logo.png'" alt="4Food" class="welcome-logo" />
                    <div class="welcome-text">
                        <h1>{{ greeting }}, {{ firstName }}! 👋</h1>
                        <p class="welcome-role">{{ roleLabels[role] ?? 'Departamento' }}</p>
                        <p class="welcome-date">{{ today }}</p>
                    </div>
                </section>

                <p class="welcome-lead">O que você quer fazer agora?</p>

                <div class="welcome-actions">
                    <button
                        v-for="action in actions"
                        :key="action.route"
                        type="button"
                        class="welcome-card"
                        @click="go(action.route)"
                    >
                        <span class="welcome-card-icon">
                            <svg v-if="action.icon === 'orders'" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 17H6v-2h12v2zm0-4H6v-2h12v2zm0-4H6V7h12v2zM3 22l1.5-1.5L6 22l1.5-1.5L9 22l1.5-1.5L12 22l1.5-1.5L15 22l1.5-1.5L18 22l1.5-1.5L21 22V2l-1.5 1.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2 4.5 3.5 3 2v20z" />
                            </svg>
                            <svg v-else-if="action.icon === 'tables'" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 3H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 2v3H5V5h15zM5 10h4v9H5v-9zm6 0h4v9h-4v-9zm6 0h3v9h-3v-9z" />
                            </svg>
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1H5V5h14v14.1zm0-16.1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
                            </svg>
                        </span>
                        <span class="welcome-card-text">
                            <strong>{{ action.label }}</strong>
                            <small>{{ action.desc }}</small>
                        </span>
                        <span class="welcome-card-arrow" aria-hidden="true">→</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.shell {
    min-height: 100vh;
    display: flex;
    background: var(--bg-f6f7f9);
}

.main {
    flex: 1;
    min-width: 0;
}

.content {
    padding: 28px 24px;
    max-width: 760px;
}

.welcome-hero {
    display: flex;
    align-items: center;
    gap: 18px;
    background: var(--bg-ffffff);
    border: 1px solid var(--bd-eceef0);
    border-left: 4px solid var(--bg-993c1d);
    border-radius: 16px;
    padding: 22px 24px;
}

.welcome-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    flex-shrink: 0;
}

.welcome-text h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--fg-17181e);
}

.welcome-role {
    margin: 4px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--fg-993c1d);
}

.welcome-date {
    margin: 2px 0 0;
    font-size: 12px;
    color: var(--fg-7b7f89);
    text-transform: capitalize;
}

.welcome-lead {
    margin: 26px 2px 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--fg-5f6572);
}

.welcome-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 12px;
}

.welcome-card {
    display: flex;
    align-items: center;
    gap: 14px;
    text-align: left;
    background: var(--bg-ffffff);
    border: 1px solid var(--bd-eceef0);
    border-radius: 14px;
    padding: 16px 18px;
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
}

.welcome-card:hover {
    transform: translateY(-2px);
    border-color: var(--bd-f0d7ce);
    box-shadow: 0 8px 22px rgb(153 60 29 / 10%);
}

.welcome-card-icon {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 12px;
    background: var(--bg-faece7);
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-card-icon svg {
    width: 22px;
    height: 22px;
    fill: var(--fg-993c1d);
}

.welcome-card-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.welcome-card-text strong {
    font-size: 15px;
    color: var(--fg-17181e);
}

.welcome-card-text small {
    font-size: 12px;
    color: var(--fg-7b7f89);
}

.welcome-card-arrow {
    font-size: 18px;
    color: var(--fg-993c1d);
    flex-shrink: 0;
}
</style>
