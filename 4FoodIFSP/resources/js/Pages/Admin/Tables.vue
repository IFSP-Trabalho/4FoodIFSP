<script setup>
import './styles/Tables.css';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import TableStatusCard from '../../Components/TableStatusCard.vue';
import TableFormModal from '../../Components/TableFormModal.vue';
import CloseAccountModal from '../../Components/CloseAccountModal.vue';
import TablesLayoutSwitch from '../../Components/TablesLayoutSwitch.vue';
import { useTablesAdmin } from '../../composables/useTablesAdmin.js';

const props = defineProps({
    tables: { type: Array,  default: () => [] },
    stats:  { type: Object, default: () => ({}) },
});

// Cadastro de mesas (criar/editar/excluir) é exclusivo do admin.
const isAdmin = computed(() => usePage().props.auth?.user?.role === 'admin');

const {
    search,
    filterStatus,
    showModal,
    editingTable,
    selectedTable,
    actionLoading,
    actionError,
    flashSuccess,
    deleteError,
    statusLabel,
    filterTabs,
    filtered,
    showCloseModal,
    closeAccountTarget,
    closeAccountError,
    openCreate,
    openEdit,
    closeModal,
    onTableSelect,
    confirmDelete,
    confirmRelease,
    confirmCloseAccount,
    cancelCloseAccount,
    submitCloseAccount,
    formatDateTime,
} = useTablesAdmin(props);
</script>

<template>
    <div class="shell">
        <AppSidebar active="tables" />

        <div class="main">
            <AppTopbar
                title="Mesas"
                :subtitle="`${stats.total ?? 0} mesas cadastradas`"
            />

            <div class="content">
                <p v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</p>
                <p v-if="deleteError"  class="flash flash--error">{{ deleteError }}</p>

                <header class="page-head">
                    <div class="page-head-row">
                        <div class="page-head-left">
                            <TablesLayoutSwitch active="salao" />
                            <div class="search-wrap">
                                <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/>
                                </svg>
                                <input
                                    v-model="search"
                                    type="search"
                                    class="search-input"
                                    placeholder="Buscar por número ou nome..."
                                    aria-label="Buscar mesa"
                                />
                            </div>
                        </div>

                        <button v-if="isAdmin" type="button" class="btn-new" @click="openCreate">
                            Nova mesa
                        </button>
                    </div>

                    <div class="filter-tabs" role="tablist" aria-label="Filtrar mesas">
                        <button
                            v-for="tab in filterTabs"
                            :key="tab.value"
                            type="button"
                            role="tab"
                            class="filter-tab"
                            :class="{ 'filter-tab--active': filterStatus === tab.value }"
                            :aria-selected="filterStatus === tab.value"
                            @click="filterStatus = tab.value"
                        >
                            {{ tab.label }}
                            <span class="filter-tab-count">{{ tab.count }}</span>
                        </button>
                    </div>
                </header>

                <div class="workspace" :class="{ 'workspace--has-detail': !!selectedTable }">
                    <section class="floor" aria-label="Mapa de mesas">
                        <div v-if="filtered.length > 0" class="tables-grid">
                            <TableStatusCard
                                v-for="table in filtered"
                                :key="table.id"
                                :table="table"
                                :selected="selectedTable?.id === table.id"
                                @click="onTableSelect"
                            />
                        </div>

                        <div v-else class="empty-state">
                            <template v-if="props.tables.length === 0">
                                <p class="empty-title">Nenhuma mesa ainda</p>
                                <p class="empty-text">Cadastre a primeira mesa para o salão aparecer aqui.</p>
                                <button v-if="isAdmin" type="button" class="btn-new btn-new--inline" @click="openCreate">
                                    Nova mesa
                                </button>
                            </template>
                            <template v-else>
                                <p class="empty-title">Nada por aqui</p>
                                <p class="empty-text">Nenhuma mesa bate com a busca ou o filtro escolhido.</p>
                            </template>
                        </div>
                    </section>

                    <aside
                        v-if="selectedTable"
                        class="detail"
                        aria-label="Detalhes da mesa"
                    >
                        <div class="detail-header">
                            <div class="detail-heading">
                                <span class="detail-eyebrow">Mesa</span>
                                <h2 class="detail-number">{{ selectedTable.number }}</h2>
                                <p v-if="selectedTable.label" class="detail-label">{{ selectedTable.label }}</p>
                            </div>
                            <span
                                class="detail-status"
                                :class="`detail-status--${selectedTable.status}`"
                            >
                                {{ statusLabel[selectedTable.status] ?? selectedTable.status }}
                            </span>
                        </div>

                        <dl class="detail-facts">
                            <div class="detail-fact">
                                <dt>Cadastro</dt>
                                <dd>{{ selectedTable.active ? 'Ativa' : 'Inativa' }}</dd>
                            </div>

                            <template v-if="selectedTable.session_started_at">
                                <div class="detail-fact">
                                    <dt>Sessão desde</dt>
                                    <dd>{{ formatDateTime(selectedTable.session_started_at) }}</dd>
                                </div>
                                <div class="detail-fact">
                                    <dt>Pedidos na sessão</dt>
                                    <dd>{{ selectedTable.orders_count }}</dd>
                                </div>
                            </template>
                        </dl>

                        <div v-if="selectedTable.total_open" class="detail-total-block">
                            <span class="detail-total-label">Total em aberto</span>
                            <span class="detail-total-value">{{ selectedTable.total_open }}</span>
                        </div>

                        <p v-if="actionError" class="detail-error">{{ actionError }}</p>

                        <div class="detail-actions">
                            <button
                                v-if="['open_bill', 'pending_release'].includes(selectedTable.status)"
                                type="button"
                                class="detail-btn detail-btn--primary"
                                :disabled="actionLoading"
                                @click="confirmCloseAccount(selectedTable)"
                            >
                                {{ actionLoading ? 'Aguarde...' : 'Fechar conta' }}
                            </button>

                            <button
                                v-if="['in_use', 'open_bill', 'pending_release'].includes(selectedTable.status)"
                                type="button"
                                class="detail-btn detail-btn--outline"
                                :disabled="actionLoading"
                                @click="confirmRelease(selectedTable)"
                            >
                                Liberar sem fechar conta
                            </button>

                            <button
                                v-if="isAdmin"
                                type="button"
                                class="detail-btn detail-btn--outline"
                                :disabled="actionLoading"
                                @click="openEdit(selectedTable)"
                            >
                                Editar mesa
                            </button>

                            <button
                                v-if="isAdmin && ['inactive', 'available'].includes(selectedTable.status)"
                                type="button"
                                class="detail-btn detail-btn--danger"
                                :disabled="actionLoading"
                                @click="confirmDelete(selectedTable)"
                            >
                                Excluir mesa
                            </button>
                        </div>
                    </aside>

                    <aside v-else class="detail detail--placeholder" aria-hidden="true">
                        <div class="detail-placeholder-inner">
                            <svg class="detail-placeholder-icon" viewBox="0 0 48 48" aria-hidden="true">
                                <rect x="6" y="14" width="36" height="22" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>
                                <path d="M14 14V10a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4v4" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p class="detail-placeholder-title">Selecione uma mesa</p>
                            <p class="detail-placeholder-text">
                                Clique em um card para ver totais, pedidos e ações da sessão.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <template v-if="showModal">
        <TableFormModal :table="editingTable" @close="closeModal" />
    </template>

    <template v-if="showCloseModal && closeAccountTarget">
        <CloseAccountModal
            :table="closeAccountTarget"
            :loading="actionLoading"
            :error="closeAccountError"
            @close="cancelCloseAccount"
            @confirm="submitCloseAccount"
        />
    </template>
</template>
