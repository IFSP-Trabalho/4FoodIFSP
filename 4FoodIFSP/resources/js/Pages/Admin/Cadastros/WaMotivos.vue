<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSidebar from '../../../Components/AppSidebar.vue';

const props = defineProps({
    reasons: { type: Array, default: () => [] },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const deleteError  = computed(() => page.props.errors?.delete ?? '');
const formErrors   = computed(() => page.props.errors ?? {});

const search = ref('');

// create
const isCreateOpen = ref(false);
const createName   = ref('');
const isCreating   = ref(false);

// edit
const editingReason = ref(null);
const editName      = ref('');
const editActive    = ref(true);
const isUpdating    = ref(false);

// delete
const pendingDelete = ref(null);
const isDeleting    = ref(false);

const filteredReasons = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.reasons;
    return props.reasons.filter((r) => r.name.toLowerCase().includes(term));
});

function openCreate() {
    createName.value   = '';
    isCreateOpen.value = true;
}

function closeCreate() {
    isCreateOpen.value = false;
}

function submitCreate() {
    if (isCreating.value || !createName.value.trim()) return;
    isCreating.value = true;
    router.post('/admin/cadastros/wa-motivos', { name: createName.value.trim() }, {
        preserveScroll: true,
        onFinish: () => { isCreating.value = false; },
        onSuccess: () => { isCreateOpen.value = false; },
    });
}

function openEdit(reason) {
    editingReason.value = reason;
    editName.value      = reason.name;
    editActive.value    = reason.active;
}

function closeEdit() {
    editingReason.value = null;
}

function submitEdit() {
    if (isUpdating.value || !editName.value.trim() || !editingReason.value) return;
    isUpdating.value = true;
    router.put(`/admin/cadastros/wa-motivos/${editingReason.value.id}`, {
        name:   editName.value.trim(),
        active: editActive.value,
    }, {
        preserveScroll: true,
        onFinish: () => { isUpdating.value = false; },
        onSuccess: () => { editingReason.value = null; },
    });
}

function openDelete(reason) {
    pendingDelete.value = reason;
}

function cancelDelete() {
    pendingDelete.value = null;
}

function confirmDelete() {
    if (isDeleting.value || !pendingDelete.value) return;
    isDeleting.value = true;
    router.delete(`/admin/cadastros/wa-motivos/${pendingDelete.value.id}`, {
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
                <h1>Motivos de Fechamento</h1>
                <div class="head-actions">
                    <input v-model="search" type="text" placeholder="Pesquisar">
                    <button type="button" @click="openCreate">
                        Adicionar
                    </button>
                </div>
            </header>

            <div class="content">
                <p v-if="flashSuccess" class="feedback success">{{ flashSuccess }}</p>
                <p v-if="deleteError" class="feedback error">{{ deleteError }}</p>

                <section class="table-card">
                    <div class="table-head">
                        <span>Nome</span>
                        <span>Status</span>
                        <span>Ações</span>
                    </div>

                    <template v-if="props.reasons.length === 0">
                        <div class="empty-state">
                            <p>Nenhum motivo cadastrado.</p>
                            <button type="button" @click="openCreate">
                                Adicionar primeiro motivo
                            </button>
                        </div>
                    </template>

                    <template v-else-if="filteredReasons.length === 0">
                        <p class="search-empty">Nenhum motivo encontrado.</p>
                    </template>

                    <template v-else>
                        <div
                            v-for="reason in filteredReasons"
                            :key="reason.id"
                            class="table-row"
                        >
                            <span class="row-name">{{ reason.name }}</span>
                            <span class="row-status">
                                <span :class="['status-badge', reason.active ? 'status-active' : 'status-inactive']">
                                    {{ reason.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </span>
                            <span class="row-actions">
                                <button type="button" class="btn-icon btn-edit" @click="openEdit(reason)">
                                    Editar
                                </button>
                                <button type="button" class="btn-icon btn-delete" @click="openDelete(reason)">
                                    Excluir
                                </button>
                            </span>
                        </div>
                    </template>
                </section>
            </div>
        </div>

        <!-- modal criar -->
        <div v-if="isCreateOpen" class="modal-overlay" @click.self="closeCreate">
            <div class="modal-dialog">
                <h3>Novo motivo</h3>
                <p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p>
                <div class="field">
                    <label class="field-label">Nome</label>
                    <input
                        v-model="createName"
                        type="text"
                        class="field-input"
                        placeholder="Ex: Problema resolvido"
                        maxlength="255"
                        autofocus
                        @keydown.enter="submitCreate"
                    >
                </div>
                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isCreating" @click="closeCreate">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primary" :disabled="isCreating || !createName.trim()" @click="submitCreate">
                        {{ isCreating ? 'Salvando...' : 'Salvar' }}
                    </button>
                </footer>
            </div>
        </div>

        <!-- modal editar -->
        <div v-if="editingReason" class="modal-overlay" @click.self="closeEdit">
            <div class="modal-dialog">
                <h3>Editar motivo</h3>
                <p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p>
                <div class="field">
                    <label class="field-label">Nome</label>
                    <input
                        v-model="editName"
                        type="text"
                        class="field-input"
                        maxlength="255"
                        @keydown.enter="submitEdit"
                    >
                </div>
                <div class="field field-toggle">
                    <label class="toggle-label">
                        <input v-model="editActive" type="checkbox" class="toggle-check">
                        <span>Ativo</span>
                    </label>
                </div>
                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isUpdating" @click="closeEdit">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primary" :disabled="isUpdating || !editName.trim()" @click="submitEdit">
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
                <h3>Excluir motivo</h3>
                <p>Deseja excluir <strong>{{ pendingDelete.name }}</strong>? Esta ação não pode ser desfeita.</p>
                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isDeleting" @click="cancelDelete">
                        Cancelar
                    </button>
                    <button type="button" class="btn-danger" :disabled="isDeleting" @click="confirmDelete">
                        {{ isDeleting ? 'Excluindo...' : 'Excluir' }}
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/WaMotivos.css"></style>
