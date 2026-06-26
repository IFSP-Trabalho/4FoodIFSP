<script setup>
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import AppSidebar from '../../../Components/AppSidebar.vue';
import WaConnectionCard from '../../../Components/WaConnectionCard.vue';
import WaConnectionCreatePanel from '../../../Components/WaConnectionCreatePanel.vue';
import WaConnectionEditPanel from '../../../Components/WaConnectionEditPanel.vue';
import WaQrCodeModal from '../../../Components/WaQrCodeModal.vue';

const props = defineProps({
    connections: {
        type: Array,
        default: () => [],
    },
    chatbots: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const isCreateOpen  = ref(false);
const editConnectionId = ref(null);
const pendingDeleteId = ref(null);
const isDeleting    = ref(false);

const editConnection = computed(() =>
    props.connections.find((c) => c.id === editConnectionId.value) ?? null
);

function handleEdit(id) {
    editConnectionId.value = id;
}

/** @type {import('vue').Ref<{connection: object, qrBase64: string|null, expiresAt: string|null, loading: boolean, error: string|null}|null>} */
const qrModal = ref(null);
const toastMessage = ref('');
let toastTimer = null;

function showToast(msg) {
    toastMessage.value = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastMessage.value = ''; }, 4000);
}

function handleDisconnect(id) {
    router.patch(`/admin/whatsapp/conexoes/${id}/disconnect`, {}, { preserveScroll: true });
}

async function handleRequestQr(id) {
    const connection = props.connections.find((c) => c.id === id);
    if (!connection) return;

    qrModal.value = { connection, qrBase64: null, expiresAt: null, loading: true, error: null };

    try {
        const { data } = await axios.post(`/admin/whatsapp/conexoes/${id}/request-qr`);

        if (data.connection_status === 'connected') {
            qrModal.value = null;
            router.reload({ only: ['connections'], preserveScroll: true });
            showToast('WhatsApp já estava conectado.');
            return;
        }

        qrModal.value = {
            connection,
            qrBase64:  data.qr_base64  ?? null,
            expiresAt: data.expires_at ?? null,
            loading:   false,
            error:     null,
        };
    } catch (e) {
        const msg = e.response?.data?.message ?? 'Erro ao gerar QR Code.';
        qrModal.value = { connection, qrBase64: null, expiresAt: null, loading: false, error: msg };
    }
}

async function handleCancelPairing(id) {
    if (qrModal.value) {
        qrModal.value = null;
    }
    router.reload({ only: ['connections'], preserveScroll: true });
}

function handleQrPaired() {
    qrModal.value = null;
    showToast('WhatsApp conectado com sucesso.');
    router.reload({ only: ['connections'], preserveScroll: true });
}

function handleDelete(id) {
    pendingDeleteId.value = id;
}

function confirmDelete() {
    if (!pendingDeleteId.value || isDeleting.value) return;

    isDeleting.value = true;

    router.delete(`/admin/whatsapp/conexoes/${pendingDeleteId.value}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            pendingDeleteId.value = null;
        },
    });
}

function cancelDelete() {
    pendingDeleteId.value = null;
}

const pendingDeleteConnection = computed(() =>
    props.connections.find((c) => c.id === pendingDeleteId.value) ?? null
);
</script>

<template>
    <div class="shell">
        <AppSidebar active="whatsapp-conexoes" />

        <div class="main">
            <header class="topbar">
                <div class="topbar-text">
                    <h1>Conexões WhatsApp</h1>
                    <p>Gerencie os canais de atendimento WhatsApp</p>
                </div>
                <button type="button" class="btn-add" @click="isCreateOpen = true">
                    + Adicionar
                </button>
            </header>

            <div class="content">
                <p v-if="flashSuccess" class="feedback success">{{ flashSuccess }}</p>
                <p v-if="toastMessage" class="feedback success">{{ toastMessage }}</p>

                <div v-if="connections.length" class="connections-grid">
                    <WaConnectionCard
                        v-for="c in connections"
                        :key="c.id"
                        :connection="c"
                        @disconnect="handleDisconnect"
                        @request-qr="handleRequestQr"
                        @cancel-pairing="handleCancelPairing"
                        @delete="handleDelete"
                        @edit="handleEdit"
                    />
                </div>
            </div>
        </div>

        <!-- Confirm delete dialog -->
        <div v-if="pendingDeleteId" class="confirm-delete-overlay" @click.self="cancelDelete">
            <div class="confirm-delete-dialog">
                <div class="confirm-delete-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M8 4h8l1 2h4v2H3V6h4l1-2Zm1 6h2v8H9v-8Zm4 0h2v8h-2v-8ZM6 8h12l-1 12H7L6 8Z" />
                    </svg>
                </div>
                <h3>Excluir conexão</h3>
                <p v-if="pendingDeleteConnection">
                    Deseja excluir <strong>{{ pendingDeleteConnection.name }}</strong>?
                    Esta ação não pode ser desfeita.
                </p>
                <footer class="confirm-delete-actions">
                    <button type="button" class="secondary" :disabled="isDeleting" @click="cancelDelete">
                        Cancelar
                    </button>
                    <button type="button" class="danger" :disabled="isDeleting" @click="confirmDelete">
                        {{ isDeleting ? 'Excluindo...' : 'Excluir' }}
                    </button>
                </footer>
            </div>
        </div>

        <WaQrCodeModal
            v-if="qrModal"
            :connection="qrModal.connection"
            :qr-base64="qrModal.qrBase64"
            :expires-at="qrModal.expiresAt"
            :loading="qrModal.loading"
            :error="qrModal.error"
            @close="qrModal = null"
            @paired="handleQrPaired"
            @cancel-pairing="handleCancelPairing"
        />

        <WaConnectionCreatePanel
            v-if="isCreateOpen"
            @close="isCreateOpen = false"
        />

        <WaConnectionEditPanel
            v-if="editConnection"
            :connection="editConnection"
            :chatbots="chatbots"
            @close="editConnectionId = null"
        />
    </div>
</template>

<style scoped src="./styles/Conexoes.css"></style>
