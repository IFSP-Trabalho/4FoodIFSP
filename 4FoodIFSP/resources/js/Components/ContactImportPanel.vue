<script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits(['close']);

const form = useForm({
    file: null,
});

const fileInput = ref(null);

function pickFile() {
    fileInput.value?.click();
}

function onFileChange(event) {
    form.file = event.target.files?.[0] ?? null;
    form.clearErrors();
}

function clearFile() {
    form.file = null;
    if (fileInput.value) fileInput.value.value = '';
}

function downloadTemplate() {
    window.location.href = '/whatsapp/contatos/modelo';
}

function handleCancel() {
    form.reset();
    form.clearErrors();
    emit('close');
}

function handleSubmit() {
    if (!form.file) {
        form.setError('file', 'Selecione um arquivo para importar.');
        return;
    }

    form.post('/whatsapp/contatos/importar', {
        forceFormData:  true,
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
        aria-labelledby="contact-import-title"
    >
        <header class="admin-modal-head">
            <h3 id="contact-import-title">Importar contatos</h3>
            <p>Envie uma planilha CSV com as colunas <strong>nome</strong> e <strong>numero</strong>.</p>
        </header>

        <form class="admin-modal-form" @submit.prevent="handleSubmit">
            <!-- Seleção do arquivo -->
            <div class="admin-modal-field">
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="import-file-input"
                    @change="onFileChange"
                >

                <button
                    v-if="!form.file"
                    type="button"
                    class="import-dropzone"
                    @click="pickFile"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 13v6H5v-6H3v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6h-2ZM11 4.83 8.41 7.41 7 6l5-5 5 5-1.41 1.41L13 4.83V16h-2V4.83Z" />
                    </svg>
                    <span class="import-dropzone-title">Selecionar arquivo</span>
                    <span class="import-dropzone-hint">Formato CSV (.csv)</span>
                </button>

                <div v-else class="import-file-chip">
                    <svg class="import-file-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 2 4 4h-4V4Z" />
                    </svg>
                    <span class="import-file-name">{{ form.file.name }}</span>
                    <button type="button" class="import-file-remove" aria-label="Remover arquivo" @click="clearFile">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18.3 5.71 12 12l6.3 6.29-1.42 1.42L10.59 13.4 4.3 19.7 2.88 18.3 9.17 12 2.88 5.71 4.3 4.29l6.29 6.3 6.29-6.3 1.42 1.42Z" />
                        </svg>
                    </button>
                </div>

                <small v-if="form.errors.file">{{ form.errors.file }}</small>
            </div>

            <!-- Modelo para download -->
            <div class="import-template">
                <div class="import-template-text">
                    <strong>Não sabe como montar a planilha?</strong>
                    <span>Baixe o modelo de exemplo e siga o formato.</span>
                </div>
                <button type="button" class="import-template-btn" @click="downloadTemplate">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 16 6 10l1.41-1.41L11 12.17V2h2v10.17l3.59-3.58L18 10l-6 6Zm-7 4v-2h14v2H5Z" />
                    </svg>
                    Baixar modelo
                </button>
            </div>

            <footer class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="form.processing" @click="handleCancel">
                    Cancelar
                </button>
                <button type="submit" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Importando...' : 'Importar' }}
                </button>
            </footer>
        </form>
    </section>
</template>

<style scoped src="./styles/ContactImportPanel.css"></style>
