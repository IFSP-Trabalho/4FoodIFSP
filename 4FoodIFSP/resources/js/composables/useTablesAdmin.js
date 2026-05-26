import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function useTablesAdmin(props) {
    const page = usePage();

    const search        = ref('');
    const filterStatus  = ref('all');
    const showModal          = ref(false);
    const editingTable       = ref(null);
    const selectedTable      = ref(null);
    const actionLoading      = ref(false);
    const actionError        = ref('');
    const showCloseModal     = ref(false);
    const closeAccountTarget = ref(null);
    const closeAccountError  = ref('');

    const flashSuccess = computed(() => page.props.flash?.success ?? '');
    const deleteError  = computed(() => page.props.errors?.delete ?? '');

    const statusLabel = {
        available:       'Disponível',
        in_use:          'Em uso',
        open_bill:       'Conta aberta',
        pending_release: 'Aguardando liberação',
        inactive:        'Inativa',
    };

    const kanbanColumns = [
        { key: 'available',       label: 'Disponível',    accent: '#1d9e75' },
        { key: 'in_use',          label: 'Em uso',        accent: '#ef9f27' },
        { key: 'open_bill',       label: 'Conta aberta',  accent: '#d85a30' },
        { key: 'pending_release', label: 'Aguardando',    accent: '#6b7280' },
        { key: 'inactive',        label: 'Inativas',      accent: '#d1d5db' },
    ];

    const filterTabs = computed(() => {
        const count = (status) =>
            status === 'all'
                ? props.tables.length
                : props.tables.filter((t) => t.status === status).length;

        return [
            { value: 'all',             label: 'Todas',        count: count('all') },
            { value: 'available',       label: 'Disponíveis',  count: count('available') },
            { value: 'in_use',          label: 'Em uso',       count: count('in_use') },
            { value: 'open_bill',       label: 'Conta aberta', count: count('open_bill') },
            { value: 'pending_release', label: 'Aguardando',   count: count('pending_release') },
            { value: 'inactive',        label: 'Inativas',     count: count('inactive') },
        ];
    });

    const filtered = computed(() => {
        let list = props.tables;
        const term = search.value.trim().toLowerCase();
        if (term) {
            list = list.filter((t) =>
                String(t.number).includes(term) ||
                (t.label ?? '').toLowerCase().includes(term)
            );
        }
        if (filterStatus.value !== 'all') {
            list = list.filter((t) => t.status === filterStatus.value);
        }
        return list;
    });

    const kanbanGrouped = computed(() => {
        const groups = Object.fromEntries(kanbanColumns.map((c) => [c.key, []]));
        for (const table of filtered.value) {
            if (groups[table.status]) groups[table.status].push(table);
        }
        for (const key of Object.keys(groups)) {
            groups[key].sort((a, b) => a.number - b.number);
        }
        return groups;
    });

    function openCreate() {
        editingTable.value  = null;
        showModal.value     = true;
        selectedTable.value = null;
    }

    function openEdit(table) {
        editingTable.value  = table;
        showModal.value     = true;
        selectedTable.value = null;
    }

    function closeModal() {
        showModal.value    = false;
        editingTable.value = null;
    }

    function onTableSelect(table) {
        selectedTable.value = selectedTable.value?.id === table.id ? null : table;
        actionError.value   = '';
    }

    function clearSelection() {
        selectedTable.value = null;
        actionError.value   = '';
    }

    function confirmDelete(table) {
        if (!window.confirm(`Excluir Mesa ${table.number}? Essa ação não pode ser desfeita.`)) return;
        router.delete(`/admin/mesas/${table.id}`, {
            preserveScroll: true,
            onSuccess: () => { selectedTable.value = null; },
        });
    }

    function confirmRelease(table) {
        if (!window.confirm(`Liberar Mesa ${table.number}?\nA sessão será encerrada sem fechar a conta.`)) return;
        actionLoading.value = true;
        actionError.value   = '';
        router.post(`/admin/mesas/${table.id}/release`, {}, {
            preserveScroll: true,
            onSuccess: () => { selectedTable.value = null; },
            onError:   (e) => { actionError.value = e.release ?? 'Erro ao liberar mesa.'; },
            onFinish:  () => { actionLoading.value = false; },
        });
    }

    function confirmCloseAccount(table) {
        closeAccountTarget.value = table;
        closeAccountError.value  = '';
        showCloseModal.value     = true;
    }

    function cancelCloseAccount() {
        showCloseModal.value     = false;
        closeAccountTarget.value = null;
        closeAccountError.value  = '';
    }

    function submitCloseAccount(paymentData) {
        if (!closeAccountTarget.value) return;
        actionLoading.value     = true;
        closeAccountError.value = '';
        router.post(`/admin/mesas/${closeAccountTarget.value.id}/close-account`, paymentData, {
            preserveScroll: true,
            onSuccess: () => {
                selectedTable.value      = null;
                showCloseModal.value     = false;
                closeAccountTarget.value = null;
            },
            onError: (e) => {
                closeAccountError.value = e.close_account ?? e.payment_method ?? 'Erro ao fechar conta.';
            },
            onFinish: () => { actionLoading.value = false; },
        });
    }

    function formatDateTime(iso) {
        if (!iso) return '—';
        return new Date(iso).toLocaleString('pt-BR', {
            day: '2-digit', month: '2-digit',
            hour: '2-digit', minute: '2-digit',
        });
    }

    function formatTime(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    return {
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
        kanbanColumns,
        filterTabs,
        filtered,
        kanbanGrouped,
        showCloseModal,
        closeAccountTarget,
        closeAccountError,
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
        formatDateTime,
        formatTime,
    };
}
