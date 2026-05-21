<script setup>
import { computed, ref } from 'vue';
import AppSidebar from '../../Components/AppSidebar.vue';
import WaContactRow from '../../Components/WaContactRow.vue';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },
    date: {
        type: String,
        required: true,
    },
});

const activeTab = ref('in_progress');
const search = ref('');
const selectedId = ref(null);

const tabDefs = [
    { key: 'in_progress', label: 'Em andamento' },
    { key: 'triage',      label: 'Triagem' },
    { key: 'closed',      label: 'Fechados' },
];

const currentList = computed(() => props.tickets[activeTab.value] ?? []);

const filteredList = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return currentList.value;
    return currentList.value.filter((t) => {
        const name  = (t.customer_name ?? '').toLowerCase();
        const phone = (t.phone_number ?? '').toLowerCase();
        return name.includes(term) || phone.includes(term);
    });
});

function tabCount(key) {
    return (props.tickets[key] ?? []).length;
}

function onTabChange(key) {
    activeTab.value = key;
    selectedId.value = null;
}

function onSelect(id) {
    selectedId.value = id;
}

function formatTime(iso) {
    const d   = new Date(iso);
    const now = new Date();
    const sameDay = (a, b) =>
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (sameDay(d, now))       return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    if (sameDay(d, yesterday)) return 'ontem';
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}
</script>

<template>
    <div class="shell">
        <AppSidebar active="whatsapp" />

        <!-- painel esquerdo: lista -->
        <div class="inbox-panel">
            <!-- cabeçalho do painel -->
            <div class="panel-head">
                <div class="panel-title">
                    <span class="dot" />
                    <div>
                        <h1>Atendimento</h1>
                        <p>{{ date }}</p>
                    </div>
                </div>
                <span class="role-badge">WhatsApp</span>
            </div>

            <!-- abas -->
            <nav class="tabs">
                <button
                    v-for="tab in tabDefs"
                    :key="tab.key"
                    type="button"
                    class="tab"
                    :class="{ 'tab--active': activeTab === tab.key }"
                    @click="onTabChange(tab.key)"
                >
                    {{ tab.label }}
                    <span class="tab-badge">{{ tabCount(tab.key) }}</span>
                </button>
            </nav>

            <!-- busca -->
            <div class="search-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
                </svg>
                <input
                    v-model="search"
                    type="search"
                    class="search-input"
                    placeholder="Pesquisar por nome ou número..."
                    aria-label="Pesquisar nome"
                />
            </div>

            <!-- lista vazia -->
            <div v-if="filteredList.length === 0" class="empty-list">
                <p v-if="search.trim()">Nenhum contato encontrado para "{{ search.trim() }}"</p>
                <p v-else>Nenhum chamado nesta aba</p>
            </div>

            <!-- lista de contatos -->
            <ul v-else class="contact-list">
                <WaContactRow
                    v-for="ticket in filteredList"
                    :key="ticket.id"
                    :ticket="ticket"
                    :selected="selectedId === ticket.id"
                    :show-novo-badge="activeTab === 'triage'"
                    :time-label="formatTime(ticket.updated_at)"
                    @select="onSelect"
                />
            </ul>
        </div>

        <!-- painel direito: detalhe vazio -->
        <div class="detail-panel">
            <div class="detail-empty">
                <div class="detail-logo">
                    <img :src="'/img/logo.png'" alt="2MP Soft" />
                </div>
                <p>Selecione um ticket para iniciar o atendimento</p>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Inbox.css"></style>
