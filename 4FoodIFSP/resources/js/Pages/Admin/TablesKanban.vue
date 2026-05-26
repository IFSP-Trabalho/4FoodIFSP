<script setup>
import './styles/TablesKanban.css';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import TableFormModal from '../../Components/TableFormModal.vue';
import CloseAccountModal from '../../Components/CloseAccountModal.vue';
import TableKanbanChip from '../../Components/TableKanbanChip.vue';
import TablesLayoutSwitch from '../../Components/TablesLayoutSwitch.vue';
import { useTablesAdmin } from '../../composables/useTablesAdmin.js';

const props = defineProps({
    tables: { type: Array,  default: () => [] },
    stats:  { type: Object, default: () => ({}) },
});

const {
    search,
    showModal,
    editingTable,
    selectedTable,
    actionLoading,
    actionError,
    flashSuccess,
    deleteError,
    statusLabel,
    kanbanColumns,
    filtered,
    kanbanGrouped,
    openCreate,
    openEdit,
    closeModal,
    onTableSelect,
    clearSelection,
    confirmDelete,
    confirmRelease,
    confirmCloseAccount,
    cancelCloseAccount,
    submitCloseAccount,
    showCloseModal,
    closeAccountTarget,
    closeAccountError,
    formatDateTime,
} = useTablesAdmin(props);
</script>

<template>
    <div class="shell" :class="{ 'shell--dock-open': !!selectedTable }">
        <AppSidebar active="tables" />

        <div class="main">
            <AppTopbar
                title="Mesas"
                :subtitle="`${stats.total ?? 0} mesas · visão operacional`"
                role-badge="Admin"
            />

            <div class="content">
                <p v-if="flashSuccess" class="flash flash--success">{{ flashSuccess }}</p>
                <p v-if="deleteError"  class="flash flash--error">{{ deleteError }}</p>

                <header class="ops-head">
                    <div class="ops-head-left">
                        <TablesLayoutSwitch active="operacional" />
                        <div class="search-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/>
                            </svg>
                            <input
                                v-model="search"
                                type="search"
                                class="search-input"
                                placeholder="Filtrar mesa..."
                                aria-label="Filtrar mesa"
                            />
                        </div>
                    </div>
                    <button type="button" class="btn-new" @click="openCreate">Nova mesa</button>
                </header>

                <div v-if="filtered.length > 0" class="kanban-board">
                    <section
                        v-for="col in kanbanColumns"
                        :key="col.key"
                        class="kanban-col"
                        :aria-label="col.label"
                    >
                        <header class="kanban-col-head" :style="{ '--col-accent': col.accent }">
                            <span class="kanban-col-title">{{ col.label }}</span>
                            <span class="kanban-col-count">{{ kanbanGrouped[col.key].length }}</span>
                        </header>

                        <div class="kanban-col-body">
                            <TableKanbanChip
                                v-for="table in kanbanGrouped[col.key]"
                                :key="table.id"
                                :table="table"
                                :accent="col.accent"
                                :selected="selectedTable?.id === table.id"
                                @click="onTableSelect"
                            />

                            <p v-if="kanbanGrouped[col.key].length === 0" class="kanban-col-empty">
                                —
                            </p>
                        </div>
                    </section>
                </div>

                <div v-else class="empty-state">
                    <template v-if="props.tables.length === 0">
                        <p class="empty-title">Nenhuma mesa cadastrada</p>
                        <button type="button" class="btn-new" @click="openCreate">Nova mesa</button>
                    </template>
                    <template v-else>
                        <p class="empty-title">Nenhum resultado</p>
                        <p class="empty-text">Ajuste a busca para encontrar uma mesa.</p>
                    </template>
                </div>
            </div>
        </div>

        <Transition name="dock">
            <footer v-if="selectedTable" class="dock" aria-label="Ações da mesa selecionada">
                <div class="dock-inner">
                    <button type="button" class="dock-close" aria-label="Fechar" @click="clearSelection">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <div class="dock-info">
                        <div class="dock-mesa">
                            <span class="dock-mesa-num">{{ selectedTable.number }}</span>
                            <div class="dock-mesa-text">
                                <strong>Mesa {{ selectedTable.number }}</strong>
                                <span v-if="selectedTable.label">{{ selectedTable.label }}</span>
                            </div>
                        </div>

                        <div class="dock-stats">
                            <span class="dock-pill" :class="`dock-pill--${selectedTable.status}`">
                                {{ statusLabel[selectedTable.status] }}
                            </span>
                            <span v-if="selectedTable.session_started_at" class="dock-stat">
                                desde {{ formatDateTime(selectedTable.session_started_at) }}
                            </span>
                            <span v-if="selectedTable.orders_count > 0" class="dock-stat">
                                {{ selectedTable.orders_count }} pedido(s)
                            </span>
                        </div>

                        <div v-if="selectedTable.total_open" class="dock-total">
                            {{ selectedTable.total_open }}
                        </div>
                    </div>

                    <p v-if="actionError" class="dock-error">{{ actionError }}</p>

                    <div class="dock-actions">
                        <button
                            v-if="['open_bill', 'pending_release'].includes(selectedTable.status)"
                            type="button"
                            class="dock-btn dock-btn--primary"
                            :disabled="actionLoading"
                            @click="confirmCloseAccount(selectedTable)"
                        >
                            {{ actionLoading ? '...' : 'Fechar conta' }}
                        </button>

                        <button
                            v-if="['in_use', 'open_bill', 'pending_release'].includes(selectedTable.status)"
                            type="button"
                            class="dock-btn dock-btn--ghost"
                            :disabled="actionLoading"
                            @click="confirmRelease(selectedTable)"
                        >
                            Liberar
                        </button>

                        <button
                            type="button"
                            class="dock-btn dock-btn--ghost"
                            :disabled="actionLoading"
                            @click="openEdit(selectedTable)"
                        >
                            Editar
                        </button>

                        <button
                            v-if="['inactive', 'available'].includes(selectedTable.status)"
                            type="button"
                            class="dock-btn dock-btn--danger"
                            :disabled="actionLoading"
                            @click="confirmDelete(selectedTable)"
                        >
                            Excluir
                        </button>
                    </div>
                </div>
            </footer>
        </Transition>
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
