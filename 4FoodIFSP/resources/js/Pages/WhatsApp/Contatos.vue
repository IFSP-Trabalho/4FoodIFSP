<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppSidebar from '../../Components/AppSidebar.vue';
import ContactFormPanel from '../../Components/ContactFormPanel.vue';

const props = defineProps({
    contacts:   { type: Array,  default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
    filters:    { type: Object, default: () => ({ name: '', number: '' }) },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const deleteError  = computed(() => page.props.errors?.delete ?? '');

const filterName   = ref(props.filters.name ?? '');
const filterNumber = ref(props.filters.number ?? '');

let filterTimer = null;

function applyFilters(page = 1) {
    router.get('/whatsapp/contatos', {
        name:   filterName.value.trim() || undefined,
        number: filterNumber.value.trim() || undefined,
        page,
    }, {
        preserveState:  true,
        preserveScroll: true,
        replace:        true,
        only:           ['contacts', 'pagination', 'filters'],
    });
}

function onFilterInput() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => applyFilters(1), 350);
}

function clearFilters() {
    clearTimeout(filterTimer);
    filterName.value   = '';
    filterNumber.value = '';
    applyFilters(1);
}

function reload() {
    router.reload({ only: ['contacts', 'pagination', 'filters'], preserveScroll: true });
}

function goToPage(target) {
    if (target < 1 || target > props.pagination.last_page || target === props.pagination.current_page) {
        return;
    }
    applyFilters(target);
}

function formatNumber(contact) {
    const ddd = contact.ddd;
    const num = contact.number;
    if (ddd && num) {
        const formatted = num.length === 9
            ? `${num.slice(0, 5)}-${num.slice(5)}`
            : num.length === 8
                ? `${num.slice(0, 4)}-${num.slice(4)}`
                : num;
        return `(${ddd}) ${formatted}`;
    }
    return contact.phone_digits;
}

/* ---------- menu de 3 pontos ---------- */
const openMenuId = ref(null);

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function onWindowClick(event) {
    if (!(event.target instanceof HTMLElement)) return;
    if (!event.target.closest('.menu-wrapper')) {
        openMenuId.value = null;
    }
}

onMounted(() => window.addEventListener('click', onWindowClick));
onBeforeUnmount(() => window.removeEventListener('click', onWindowClick));

/* ---------- abrir atendimento ---------- */
const openingId = ref(null);

function openTicket(contact) {
    if (openingId.value) return;
    openingId.value = contact.id;
    router.post(`/whatsapp/contatos/${contact.id}/abrir-atendimento`, {}, {
        onFinish: () => { openingId.value = null; },
    });
}

/* ---------- modal criar / editar ---------- */
const isFormOpen      = ref(false);
const editingContact  = ref(null);

function openCreate() {
    openMenuId.value = null;
    editingContact.value = null;
    isFormOpen.value = true;
}

function openEdit(contact) {
    openMenuId.value = null;
    editingContact.value = contact;
    isFormOpen.value = true;
}

function closeForm() {
    isFormOpen.value = false;
    editingContact.value = null;
}

/* ---------- remover ---------- */
const pendingDelete = ref(null);
const isDeleting    = ref(false);

function openDelete(contact) {
    openMenuId.value = null;
    pendingDelete.value = contact;
}

function cancelDelete() {
    pendingDelete.value = null;
}

function confirmDelete() {
    if (isDeleting.value || !pendingDelete.value) return;
    isDeleting.value = true;
    router.delete(`/whatsapp/contatos/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => { isDeleting.value = false; pendingDelete.value = null; },
    });
}
</script>

<template>
    <div class="shell">
        <AppSidebar active="whatsapp-contatos" />

        <div class="main">
            <header class="topbar">
                <h1>Contatos</h1>
                <div class="head-actions">
                    <button type="button" class="btn-reload" title="Recarregar" aria-label="Recarregar" @click="reload">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.74 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35Z" />
                        </svg>
                    </button>

                    <div class="split-wrapper">
                        <button type="button" class="btn-secondary-soft" disabled title="Em breve">
                            Exportar / Importar
                        </button>
                        <button type="button" class="btn-split-arrow" disabled aria-label="Opções" title="Em breve">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5z" /></svg>
                        </button>
                    </div>

                    <button type="button" class="btn-primary" @click="openCreate">
                        Adicionar contato
                    </button>
                </div>
            </header>

            <div class="filters">
                <input
                    v-model="filterName"
                    type="text"
                    placeholder="Filtrar por nome"
                    @input="onFilterInput"
                >
                <input
                    v-model="filterNumber"
                    type="text"
                    placeholder="Filtrar por número"
                    @input="onFilterInput"
                >
                <button type="button" class="btn-clear" @click="clearFilters">Limpar</button>
            </div>

            <div class="content">
                <p v-if="flashSuccess" class="feedback success">{{ flashSuccess }}</p>
                <p v-if="deleteError" class="feedback error">{{ deleteError }}</p>

                <section class="table-card">
                    <div class="table-head">
                        <span>Nome</span>
                        <span>Número</span>
                        <span>Ações</span>
                    </div>

                    <template v-if="contacts.length === 0">
                        <div class="empty-state">
                            <p>Nenhum contato encontrado.</p>
                        </div>
                    </template>

                    <template v-else>
                        <div v-for="contact in contacts" :key="contact.id" class="table-row">
                            <span class="row-name">{{ contact.name || '—' }}</span>
                            <span class="row-number">{{ formatNumber(contact) }}</span>
                            <span class="row-actions">
                                <button
                                    type="button"
                                    class="btn-icon btn-open"
                                    :disabled="openingId === contact.id"
                                    @click="openTicket(contact)"
                                >
                                    {{ openingId === contact.id ? 'Abrindo…' : 'Abrir Atendimento' }}
                                </button>
                                <button type="button" class="btn-icon btn-edit" @click="openEdit(contact)">
                                    Editar
                                </button>

                                <div class="menu-wrapper">
                                    <button
                                        type="button"
                                        class="btn-dots"
                                        aria-label="Mais ações"
                                        @click.stop="toggleMenu(contact.id)"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                                        </svg>
                                    </button>

                                    <div v-if="openMenuId === contact.id" class="row-menu">
                                        <button type="button" class="row-menu-item danger" @click="openDelete(contact)">
                                            Remover contato
                                        </button>
                                        <button type="button" class="row-menu-item" disabled title="Em breve">
                                            Histórico de chamados
                                        </button>
                                    </div>
                                </div>
                            </span>
                        </div>
                    </template>
                </section>

                <div v-if="pagination.last_page > 1" class="pagination">
                    <button
                        type="button"
                        class="page-arrow"
                        :disabled="pagination.current_page <= 1"
                        aria-label="Página anterior"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6z" /></svg>
                    </button>
                    <span class="page-indicator">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                    <button
                        type="button"
                        class="page-arrow"
                        :disabled="pagination.current_page >= pagination.last_page"
                        aria-label="Próxima página"
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6z" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- modal criar / editar -->
        <ContactFormPanel
            v-if="isFormOpen"
            :contact="editingContact"
            @close="closeForm"
        />

        <!-- modal remover -->
        <div v-if="pendingDelete" class="modal-overlay" @click.self="cancelDelete">
            <div class="modal-dialog modal-dialog--center">
                <div class="delete-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M8 4h8l1 2h4v2H3V6h4l1-2Zm1 6h2v8H9v-8Zm4 0h2v8h-2v-8ZM6 8h12l-1 12H7L6 8Z" />
                    </svg>
                </div>
                <h3>Remover contato</h3>
                <p>Deseja remover <strong>{{ pendingDelete.name || formatNumber(pendingDelete) }}</strong>? Esta ação não pode ser desfeita.</p>
                <footer class="modal-actions">
                    <button type="button" class="btn-secondary" :disabled="isDeleting" @click="cancelDelete">
                        Cancelar
                    </button>
                    <button type="button" class="btn-danger" :disabled="isDeleting" @click="confirmDelete">
                        {{ isDeleting ? 'Removendo...' : 'Remover' }}
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Contatos.css"></style>
