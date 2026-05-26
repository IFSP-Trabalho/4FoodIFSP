<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    table: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = ref({
    number: '',
    label:  '',
    active: true,
});
const submitting = ref(false);
const errors     = ref({});

watch(() => props.table, (t) => {
    if (t) {
        form.value = { number: t.number, label: t.label ?? '', active: t.active };
    } else {
        form.value = { number: '', label: '', active: true };
    }
    errors.value = {};
}, { immediate: true });

function close() {
    emit('close');
}

function submit() {
    if (submitting.value) return;
    submitting.value = true;
    errors.value = {};

    const isEdit = !!props.table;
    const url    = isEdit ? `/admin/mesas/${props.table.id}` : '/admin/mesas';
    const method = isEdit ? 'put' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (e) => { errors.value = e; },
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <div class="admin-modal-overlay" @click.self="close" />
    <div class="admin-modal" role="dialog" aria-modal="true">
        <div class="admin-modal-head">
            <h3>{{ table ? 'Editar mesa' : 'Nova mesa' }}</h3>
        </div>

        <form class="admin-modal-form" @submit.prevent="submit">
            <div class="admin-modal-field">
                <div class="admin-modal-input-wrap">
                    <span class="admin-modal-floating-label">Número *</span>
                    <input
                        v-model.number="form.number"
                        type="number"
                        min="1"
                        max="99"
                        placeholder="Ex.: 5"
                        required
                    />
                </div>
                <small v-if="errors.number">{{ errors.number }}</small>
            </div>

            <div class="admin-modal-field">
                <div class="admin-modal-input-wrap">
                    <span class="admin-modal-floating-label">Rótulo</span>
                    <input
                        v-model="form.label"
                        type="text"
                        maxlength="80"
                        placeholder="Ex.: Varanda 3 (opcional)"
                    />
                </div>
                <small v-if="errors.label">{{ errors.label }}</small>
                <small class="admin-modal-hint">Se vazio, usa "Mesa {número}".</small>
            </div>

            <div class="admin-modal-field">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                    <input v-model="form.active" type="checkbox" />
                    Mesa ativa
                </label>
                <small v-if="errors.active">{{ errors.active }}</small>
            </div>

            <p v-if="errors.number" class="admin-modal-error">{{ errors.number }}</p>

            <div class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="submitting" @click="close">Cancelar</button>
                <button type="submit" class="primary" :disabled="submitting">
                    {{ submitting ? 'Salvando...' : (table ? 'Salvar' : 'Cadastrar') }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped src="./styles/AdminModal.css"></style>
