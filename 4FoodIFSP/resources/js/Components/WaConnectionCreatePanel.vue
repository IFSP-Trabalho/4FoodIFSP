<script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import WaChannelTypeSelect from './WaChannelTypeSelect.vue';

const emit = defineEmits(['close']);

const form = useForm({
    channel_type: 'whatsapp',
    name: '',
});

function handleCancel() {
    form.reset();
    form.clearErrors();
    emit('close');
}

function handleOverlayClick() {
    handleCancel();
}

function handleSubmit() {
    form.post('/admin/whatsapp/conexoes', {
        preserveScroll: true,
        onSuccess: () => {
            handleCancel();
        },
    });
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        handleCancel();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="admin-modal-overlay" @click.self="handleOverlayClick"></div>
    <section
        class="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="wa-create-panel-title"
    >
        <header class="admin-modal-head">
            <h3 id="wa-create-panel-title">Nova conexão</h3>
        </header>

        <form class="admin-modal-form" @submit.prevent="handleSubmit">
            <div class="admin-modal-field">
                <WaChannelTypeSelect
                    :model-value="form.channel_type"
                    :error="form.errors.channel_type"
                    @update:model-value="(v) => { form.channel_type = v; }"
                />
            </div>

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

            <p v-if="form.errors.channel_type" class="admin-modal-error">
                {{ form.errors.channel_type }}
            </p>

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

<style scoped src="./styles/WaConnectionCreatePanel.css"></style>
