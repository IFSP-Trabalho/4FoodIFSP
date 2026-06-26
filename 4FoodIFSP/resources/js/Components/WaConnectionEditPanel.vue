<script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    connection: { type: Object, required: true },
    chatbots:   { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const activeTab = ref('geral');

const form = useForm({
    name:            props.connection.name ?? '',
    active:          props.connection.active ?? true,
    chatbot_flow_id: props.connection.chatbot_flow_id ?? '',
});

function handleCancel() {
    form.reset();
    form.clearErrors();
    emit('close');
}

function handleSubmit() {
    form.put(`/admin/whatsapp/conexoes/${props.connection.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function onKeydown(event) {
    if (event.key === 'Escape') handleCancel();
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="admin-modal-overlay" @click.self="handleCancel"></div>
    <section
        class="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="wa-edit-panel-title"
    >
        <header class="admin-modal-head">
            <h3 id="wa-edit-panel-title">Editar conexão</h3>
        </header>

        <div class="wa-edit-tabs">
            <button type="button" :class="{ active: activeTab === 'geral' }" @click="activeTab = 'geral'">
                Geral
            </button>
            <button type="button" :class="{ active: activeTab === 'chatbot' }" @click="activeTab = 'chatbot'">
                ChatBot
            </button>
        </div>

        <form class="admin-modal-form" @submit.prevent="handleSubmit">
            <!-- ── Geral: nome + ativo ─────────────────────────────── -->
            <template v-if="activeTab === 'geral'">
                <label class="admin-modal-field">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">Nome</span>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder=" "
                            required
                            minlength="2"
                            maxlength="80"
                            autocomplete="off"
                        >
                    </div>
                    <small v-if="form.errors.name">{{ form.errors.name }}</small>
                </label>

                <div class="admin-modal-field">
                    <label class="wa-toggle">
                        <input v-model="form.active" type="checkbox">
                        <span class="wa-toggle-track"><span class="wa-toggle-thumb"></span></span>
                        <span class="wa-toggle-label">{{ form.active ? 'Ativa' : 'Inativa' }}</span>
                    </label>
                </div>
            </template>

            <!-- ── ChatBot: vincular um fluxo criado ───────────────── -->
            <template v-else>
                <label class="admin-modal-field">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">Chatbot vinculado</span>
                        <select v-model="form.chatbot_flow_id" class="wa-select">
                            <option value="">Nenhum</option>
                            <option v-for="bot in chatbots" :key="bot.id" :value="bot.id">
                                {{ bot.name }}
                            </option>
                        </select>
                    </div>
                    <small v-if="form.errors.chatbot_flow_id">{{ form.errors.chatbot_flow_id }}</small>
                    <small class="admin-modal-hint">
                        O chatbot selecionado responde automaticamente nesta conexão.
                    </small>
                </label>

                <p v-if="!chatbots.length" class="admin-modal-warning">
                    Nenhum chatbot criado ainda. Crie um em ChatBot para poder vincular.
                </p>
            </template>

            <footer class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="form.processing" @click="handleCancel">
                    Sair
                </button>
                <button type="submit" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Salvando...' : 'Salvar' }}
                </button>
            </footer>
        </form>
    </section>
</template>

<style scoped>
.wa-edit-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--bd-eceef0);
}

.wa-edit-tabs button {
    border: 0;
    background: transparent;
    padding: 8px 14px;
    margin-bottom: -1px;
    font-size: 13px;
    font-weight: 600;
    color: var(--fg-7b7f89);
    cursor: pointer;
    border-bottom: 2px solid transparent;
}

.wa-edit-tabs button.active {
    color: var(--fg-993c1d);
    border-bottom-color: var(--bd-993c1d);
}

.wa-select {
    width: 100%;
    border: 0;
    background: transparent;
    outline: 0;
    font-size: 13px;
    color: var(--fg-1f242e);
    padding: 11px 0;
}

/* Toggle ativo/inativo */
.wa-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.wa-toggle input {
    display: none;
}

.wa-toggle-track {
    width: 40px;
    height: 22px;
    border-radius: 999px;
    background: var(--bg-d9dde3);
    position: relative;
    transition: background 0.15s ease;
    flex-shrink: 0;
}

.wa-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--bg-ffffff);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    transition: transform 0.15s ease;
}

.wa-toggle input:checked + .wa-toggle-track {
    background: var(--bg-993c1d);
}

.wa-toggle input:checked + .wa-toggle-track .wa-toggle-thumb {
    transform: translateX(18px);
}

.wa-toggle-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--fg-1f242e);
}
</style>
