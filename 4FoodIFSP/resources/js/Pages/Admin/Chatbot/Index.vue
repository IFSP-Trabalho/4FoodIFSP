<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSidebar from '../../../Components/AppSidebar.vue';

const props = defineProps({
    bots: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');

const filteredBots = computed(() => {
    if (!search.value.trim()) {
        return props.bots;
    }

    const query = search.value.toLowerCase();

    return props.bots.filter((bot) => (
        String(bot.name ?? '').toLowerCase().includes(query)
    ));
});

const modalMode = ref(null); // 'create' | 'edit' | null
const editingId = ref(null);

const form = useForm({
    name: '',
    active: false,
});

function openCreate() {
    modalMode.value = 'create';
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function openEdit(bot) {
    modalMode.value = 'edit';
    editingId.value = bot.id;
    form.name = bot.name;
    form.active = bot.active;
    form.clearErrors();
}

function closeModal() {
    modalMode.value = null;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submitModal() {
    if (modalMode.value === 'create') {
        form.post('/admin/chatbot', { onSuccess: () => closeModal() });
        return;
    }

    if (modalMode.value === 'edit') {
        form.put(`/admin/chatbot/${editingId.value}`, { onSuccess: () => closeModal() });
    }
}

function openFlow(bot) {
    router.visit(`/admin/chatbot/${bot.id}/flow`);
}

function removeBot(bot) {
    if (!window.confirm(`Excluir o bot "${bot.name}"?`)) {
        return;
    }

    router.delete(`/admin/chatbot/${bot.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="shell">
        <AppSidebar active="chatbot" />

        <div class="main">
            <header class="topbar">
                <h1>ChatBot</h1>
                <div class="head-actions">
                    <input v-model="search" type="text" placeholder="Localize">
                    <button type="button" @click="openCreate">
                        Adicionar
                    </button>
                </div>
            </header>

            <div class="content">
                <section class="table-card">
                    <div class="table-head">
                        <span>Nome</span>
                        <span>Ativo</span>
                        <span>Acoes</span>
                    </div>

                    <template v-if="props.bots.length === 0">
                        <div class="empty-state">
                            <p>Nenhum bot cadastrado.</p>
                        </div>
                    </template>

                    <template v-else-if="filteredBots.length === 0">
                        <p class="search-empty">Nenhum bot encontrado.</p>
                    </template>

                    <template v-else>
                        <div v-for="bot in filteredBots" :key="bot.id" class="table-row">
                            <span class="bot-name">{{ bot.name }}</span>
                            <span>
                                <span class="status-badge" :class="bot.active ? 'on' : 'off'">
                                    {{ bot.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </span>
                            <span class="row-actions">
                                <button type="button" class="action-btn" @click="openEdit(bot)">Editar</button>
                                <button type="button" class="action-btn primary" @click="openFlow(bot)">Abrir</button>
                                <button type="button" class="action-btn danger" @click="removeBot(bot)">Excluir</button>
                            </span>
                        </div>
                    </template>
                </section>
            </div>
        </div>

        <div v-if="modalMode" class="modal-overlay" @click.self="closeModal">
            <div class="modal-dialog">
                <h3>{{ modalMode === 'create' ? 'Novo bot' : 'Editar bot' }}</h3>

                <form @submit.prevent="submitModal">
                    <label class="modal-field">
                        <span>Nome</span>
                        <input v-model="form.name" type="text" required maxlength="120" placeholder="Nome do bot">
                        <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                    </label>

                    <label v-if="modalMode === 'edit'" class="modal-check">
                        <input v-model="form.active" type="checkbox">
                        <span>Ativo</span>
                    </label>

                    <footer class="modal-actions">
                        <button type="button" class="secondary" @click="closeModal">Cancelar</button>
                        <button type="submit" class="primary" :disabled="form.processing">
                            {{ form.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Index.css"></style>
