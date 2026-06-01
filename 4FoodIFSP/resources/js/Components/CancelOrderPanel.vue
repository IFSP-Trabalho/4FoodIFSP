<script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    reason: '',
});

function handleCancel() {
    form.reset();
    form.clearErrors();
    emit('close');
}

function handleSubmit() {
    form.transform((data) => ({ reason: data.reason.trim() }))
        .post(`/admin/orders/${props.order.uuid}/cancel`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
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
    <div class="admin-modal-overlay" @click.self="handleCancel"></div>
    <section
        class="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cancel-order-title"
    >
        <header class="admin-modal-head">
            <h3 id="cancel-order-title">Cancelar pedido</h3>
            <p>
                Deseja realmente cancelar o pedido <strong>#{{ order.id }}</strong> da
                {{ order.mesa }}? Esta ação não pode ser desfeita.
            </p>
        </header>

        <form class="admin-modal-form" @submit.prevent="handleSubmit">
            <label class="admin-modal-field">
                <div class="admin-modal-input-wrap admin-modal-textarea-wrap">
                    <span class="admin-modal-floating-label">Motivo do cancelamento</span>
                    <textarea
                        v-model="form.reason"
                        rows="3"
                        maxlength="500"
                        placeholder="Ex.: pedido lançado na mesa errada"
                        required
                    />
                </div>
                <small v-if="form.errors.reason">{{ form.errors.reason }}</small>
            </label>

            <footer class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="form.processing" @click="handleCancel">
                    Voltar
                </button>
                <button type="submit" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Cancelando...' : 'Cancelar pedido' }}
                </button>
            </footer>
        </form>
    </section>
</template>

<style scoped src="./styles/CancelOrderPanel.css"></style>
