<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSidebar from '../../../Components/AppSidebar.vue';

const props = defineProps({
    links:          { type: Array,  default: () => [] },
    defaultMessage: { type: String, default: '' },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const formErrors   = computed(() => page.props.errors ?? {});

const typeOptions = [
    { value: 'phone', label: 'Telefone' },
    { value: 'cpf',   label: 'CPF' },
    { value: 'cnpj',  label: 'CNPJ' },
];

const placeholders = {
    phone: 'Ex.: 11999998888',
    cpf:   'Ex.: 123.456.789-09',
    cnpj:  'Ex.: 12.345.678/0001-90',
};

const search = ref('');

const blankForm = () => ({
    type:    'phone',
    value:   '',
    label:   '',
    message: props.defaultMessage,
    active:  props.links.length === 0,
});

// create
const isCreateOpen = ref(false);
const createForm   = ref(blankForm());
const isCreating   = ref(false);

// edit
const editingLink = ref(null);
const editForm    = ref(blankForm());
const isUpdating  = ref(false);

// delete
const pendingDelete = ref(null);
const isDeleting    = ref(false);

const filteredLinks = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.links;
    return props.links.filter((l) =>
        l.value_formatted.toLowerCase().includes(term)
        || (l.label ?? '').toLowerCase().includes(term)
        || l.type_label.toLowerCase().includes(term),
    );
});

function openCreate() {
    createForm.value   = blankForm();
    isCreateOpen.value = true;
}

function closeCreate() {
    isCreateOpen.value = false;
}

function submitCreate() {
    if (isCreating.value || !createForm.value.value.trim() || !createForm.value.message.trim()) return;
    isCreating.value = true;
    router.post('/admin/cadastros/links-pagamento', { ...createForm.value }, {
        preserveScroll: true,
        onFinish: () => { isCreating.value = false; },
        onSuccess: () => { isCreateOpen.value = false; },
    });
}

function openEdit(link) {
    editingLink.value = link;
    editForm.value = {
        type:    link.type,
        value:   link.value,
        label:   link.label ?? '',
        message: link.message,
        active:  link.active,
    };
}

function closeEdit() {
    editingLink.value = null;
}

function submitEdit() {
    if (isUpdating.value || !editForm.value.value.trim() || !editForm.value.message.trim() || !editingLink.value) return;
    isUpdating.value = true;
    router.put(`/admin/cadastros/links-pagamento/${editingLink.value.id}`, { ...editForm.value }, {
        preserveScroll: true,
        onFinish: () => { isUpdating.value = false; },
        onSuccess: () => { editingLink.value = null; },
    });
}

function openDelete(link) {
    pendingDelete.value = link;
}

function cancelDelete() {
    pendingDelete.value = null;
}

function confirmDelete() {
    if (isDeleting.value || !pendingDelete.value) return;
    isDeleting.value = true;
    router.delete(`/admin/cadastros/links-pagamento/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => { isDeleting.value = false; pendingDelete.value = null; },
    });
}
</script>

<template>
    <div class="shell">
        <AppSidebar active="cadastros" />

        <div class="main">
            <header class="topbar">
                <h1>Links de pagamento</h1>
                <div class="head-actions">
                    <input v-model="search" type="text" placeholder="Pesquisar">
                    <button type="button" @click="openCreate">Adicionar</button>
                </div>
            </header>

            <div class="content">
                <p v-if="flashSuccess" class="feedback success">{{ flashSuccess }}</p>

                <p class="page-hint">
                    Cadastre uma ou mais chaves PIX (telefone, CPF ou CNPJ). A chave marcada como
                    <strong>ativa</strong> é a que será enviada ao cliente pelo atendimento.
                </p>

                <section class="table-card">
                    <div class="table-head">
                        <span>Tipo</span>
                        <span>Chave</span>
                        <span>Status</span>
                        <span>Ações</span>
                    </div>

                    <template v-if="props.links.length === 0">
                        <div class="empty-state">
                            <p>Nenhum link de pagamento cadastrado.</p>
                            <button type="button" @click="openCreate">Adicionar primeiro link</button>
                        </div>
                    </template>

                    <template v-else-if="filteredLinks.length === 0">
                        <p class="search-empty">Nenhum link encontrado.</p>
                    </template>

                    <template v-else>
                        <div v-for="link in filteredLinks" :key="link.id" class="table-row">
                            <span class="row-type">{{ link.type_label }}</span>
                            <span class="row-key">
                                <strong>{{ link.value_formatted }}</strong>
                                <small v-if="link.label">{{ link.label }}</small>
                            </span>
                            <span class="row-status">
                                <span :class="['status-badge', link.active ? 'status-active' : 'status-inactive']">
                                    {{ link.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </span>
                            <span class="row-actions">
                                <button type="button" class="btn-icon btn-edit" @click="openEdit(link)">Editar</button>
                                <button type="button" class="btn-icon btn-delete" @click="openDelete(link)">Excluir</button>
                            </span>
                        </div>
                    </template>
                </section>
            </div>
        </div>

        <!-- modal criar -->
        <div v-if="isCreateOpen" class="modal-overlay" @click.self="closeCreate">
            <div class="modal-dialog modal-dialog--lg">
                <h3>Novo link de pagamento</h3>

                <div class="field">
                    <label class="field-label">Tipo da chave</label>
                    <select v-model="createForm.type" class="field-input">
                        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">Chave PIX</label>
                    <input v-model="createForm.value" type="text" class="field-input" :placeholder="placeholders[createForm.type]" maxlength="255">
                    <p v-if="formErrors.value" class="field-error">{{ formErrors.value }}</p>
                </div>

                <div class="field">
                    <label class="field-label">Rótulo (opcional)</label>
                    <input v-model="createForm.label" type="text" class="field-input" placeholder="Ex.: Conta principal" maxlength="255">
                </div>

                <div class="field">
                    <label class="field-label">Mensagem para o cliente</label>
                    <textarea v-model="createForm.message" class="field-input field-textarea" rows="5" maxlength="4096" />
                    <p class="field-hint">Use <code>{chave}</code> onde a chave PIX deve aparecer (e <code>{tipo}</code> para o tipo). Se não usar <code>{chave}</code>, a chave é adicionada no final.</p>
                    <p v-if="formErrors.message" class="field-error">{{ formErrors.message }}</p>
                </div>

                <div class="field field-toggle">
                    <label class="toggle-label">
                        <input v-model="createForm.active" type="checkbox" class="toggle-check">
                        <span>Definir como ativo (será o link enviado ao cliente)</span>
                    </label>
                </div>

                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isCreating" @click="closeCreate">Cancelar</button>
                    <button type="button" class="btn-primary" :disabled="isCreating || !createForm.value.trim() || !createForm.message.trim()" @click="submitCreate">
                        {{ isCreating ? 'Salvando...' : 'Salvar' }}
                    </button>
                </footer>
            </div>
        </div>

        <!-- modal editar -->
        <div v-if="editingLink" class="modal-overlay" @click.self="closeEdit">
            <div class="modal-dialog modal-dialog--lg">
                <h3>Editar link de pagamento</h3>

                <div class="field">
                    <label class="field-label">Tipo da chave</label>
                    <select v-model="editForm.type" class="field-input">
                        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">Chave PIX</label>
                    <input v-model="editForm.value" type="text" class="field-input" :placeholder="placeholders[editForm.type]" maxlength="255">
                    <p v-if="formErrors.value" class="field-error">{{ formErrors.value }}</p>
                </div>

                <div class="field">
                    <label class="field-label">Rótulo (opcional)</label>
                    <input v-model="editForm.label" type="text" class="field-input" maxlength="255">
                </div>

                <div class="field">
                    <label class="field-label">Mensagem para o cliente</label>
                    <textarea v-model="editForm.message" class="field-input field-textarea" rows="5" maxlength="4096" />
                    <p class="field-hint">Use <code>{chave}</code> onde a chave PIX deve aparecer (e <code>{tipo}</code> para o tipo).</p>
                    <p v-if="formErrors.message" class="field-error">{{ formErrors.message }}</p>
                </div>

                <div class="field field-toggle">
                    <label class="toggle-label">
                        <input v-model="editForm.active" type="checkbox" class="toggle-check">
                        <span>Definir como ativo</span>
                    </label>
                </div>

                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isUpdating" @click="closeEdit">Cancelar</button>
                    <button type="button" class="btn-primary" :disabled="isUpdating || !editForm.value.trim() || !editForm.message.trim()" @click="submitEdit">
                        {{ isUpdating ? 'Salvando...' : 'Salvar' }}
                    </button>
                </footer>
            </div>
        </div>

        <!-- modal excluir -->
        <div v-if="pendingDelete" class="modal-overlay" @click.self="cancelDelete">
            <div class="modal-dialog modal-dialog--center">
                <div class="delete-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M8 4h8l1 2h4v2H3V6h4l1-2Zm1 6h2v8H9v-8Zm4 0h2v8h-2v-8ZM6 8h12l-1 12H7L6 8Z" />
                    </svg>
                </div>
                <h3>Excluir link</h3>
                <p>Deseja excluir a chave <strong>{{ pendingDelete.value_formatted }}</strong>? Esta ação não pode ser desfeita.</p>
                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isDeleting" @click="cancelDelete">Cancelar</button>
                    <button type="button" class="btn-danger" :disabled="isDeleting" @click="confirmDelete">
                        {{ isDeleting ? 'Excluindo...' : 'Excluir' }}
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/WaMotivos.css"></style>
<style scoped>
.page-hint {
    margin: 0 0 12px;
    font-size: 13px;
    color: var(--fg-5f6572);
    line-height: 1.5;
}

.page-hint strong {
    color: var(--fg-993c1d);
}

.table-head,
.table-row {
    grid-template-columns: 110px 1fr 110px 150px;
}

.row-key {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.row-key small {
    font-size: 12px;
    color: var(--fg-7b7f89);
}

.modal-dialog--lg {
    width: min(480px, 100%);
}

select.field-input {
    background: var(--bg-ffffff);
    appearance: auto;
}

.field-textarea {
    height: auto;
    min-height: 110px;
    padding: 10px;
    line-height: 1.5;
    resize: vertical;
    font-family: inherit;
}

.field-hint {
    margin: 0;
    font-size: 11px;
    color: var(--fg-9aa1ad);
    line-height: 1.45;
}

.field-hint code {
    background: var(--bg-f3f4f6);
    border-radius: 4px;
    padding: 1px 4px;
    font-size: 11px;
    color: var(--fg-993c1d);
}
</style>
