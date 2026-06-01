<script setup>
import './styles/AdminModal.css';
import './styles/CloseAccountModal.css';
import { ref, computed } from 'vue';

const props = defineProps({
    table: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const paymentMethod  = ref('');
const discountType   = ref('percent');
const discountValue  = ref('');
const tipValue       = ref('');

const subtotal = computed(() => props.table.total_open_raw ?? 0);

const cancelledOrders = computed(() => props.table.cancelled_orders ?? []);

const discountAmount = computed(() => {
    const v = parseFloat(discountValue.value) || 0;
    if (v <= 0) return 0;
    if (discountType.value === 'percent') {
        return Math.min((subtotal.value * v) / 100, subtotal.value);
    }
    return Math.min(v, subtotal.value);
});

const tipAmount = computed(() => Math.max(parseFloat(tipValue.value) || 0, 0));

const finalTotal = computed(() => Math.max(subtotal.value - discountAmount.value + tipAmount.value, 0));

const paymentMethods = [
    { value: 'pix',     label: 'PIX' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'debito',  label: 'Débito' },
    { value: 'credito', label: 'Crédito' },
    { value: 'misto',   label: 'Misto' },
];

function fmt(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function handleConfirm() {
    if (!paymentMethod.value) return;
    emit('confirm', {
        payment_method:  paymentMethod.value,
        discount_amount: discountAmount.value,
        tip_amount:      tipAmount.value,
    });
}

const canConfirm = computed(() => !!paymentMethod.value && !props.loading);
</script>

<template>
    <div class="admin-modal-overlay" @click.self="$emit('close')" />

    <div class="admin-modal cam" role="dialog" aria-modal="true" :aria-label="`Fechar conta — Mesa ${table.number}`">
        <div class="admin-modal-head">
            <h3>Fechar conta — Mesa {{ table.number }}</h3>
            <p v-if="table.label && table.label !== 'Mesa ' + table.number">{{ table.label }}</p>
        </div>

        <div class="admin-modal-form">
            <div class="admin-modal-body">

                <!-- totals -->
                <div class="cam-totals">
                    <div class="cam-total-row">
                        <span class="cam-total-label">Subtotal</span>
                        <span class="cam-total-value">{{ fmt(subtotal) }}</span>
                    </div>
                    <div v-if="discountAmount > 0" class="cam-total-row cam-total-row--discount">
                        <span class="cam-total-label">Desconto</span>
                        <span class="cam-total-value">− {{ fmt(discountAmount) }}</span>
                    </div>
                    <div v-if="tipAmount > 0" class="cam-total-row cam-total-row--tip">
                        <span class="cam-total-label">Gorjeta</span>
                        <span class="cam-total-value">+ {{ fmt(tipAmount) }}</span>
                    </div>
                    <div class="cam-total-row cam-total-row--final">
                        <span class="cam-total-label">Total final</span>
                        <span class="cam-total-value">{{ fmt(finalTotal) }}</span>
                    </div>
                </div>

                <!-- pedidos cancelados na mesa -->
                <div v-if="cancelledOrders.length" class="cam-cancelled">
                    <div class="cam-cancelled-head">
                        <span class="cam-cancelled-title">Pedidos cancelados na mesa</span>
                        <span class="cam-cancelled-badge">{{ cancelledOrders.length }}</span>
                    </div>
                    <ul class="cam-cancelled-list">
                        <li v-for="order in cancelledOrders" :key="order.id" class="cam-cancelled-item">
                            <div class="cam-cancelled-item-head">
                                <span class="cam-cancelled-id">#{{ order.id }}</span>
                                <span class="cam-cancelled-time">{{ order.time }}</span>
                            </div>
                            <p class="cam-cancelled-dishes">
                                <span v-for="(it, i) in order.items" :key="i">
                                    {{ it.qty }}x {{ it.name }}<span v-if="i < order.items.length - 1">, </span>
                                </span>
                            </p>
                            <p v-if="order.reason" class="cam-cancelled-reason">Motivo: {{ order.reason }}</p>
                        </li>
                    </ul>
                </div>

                <!-- payment method -->
                <fieldset class="cam-fieldset">
                    <legend class="cam-legend">Forma de pagamento <span class="cam-required">*</span></legend>
                    <div class="cam-methods">
                        <button
                            v-for="m in paymentMethods"
                            :key="m.value"
                            type="button"
                            class="cam-method"
                            :class="{ 'cam-method--active': paymentMethod === m.value }"
                            @click="paymentMethod = m.value"
                        >
                            {{ m.label }}
                        </button>
                    </div>
                </fieldset>

                <!-- discount -->
                <fieldset class="cam-fieldset">
                    <legend class="cam-legend">Desconto <span class="cam-hint">(opcional)</span></legend>
                    <div class="cam-discount-row">
                        <div class="cam-type-toggle">
                            <button
                                type="button"
                                class="cam-toggle-btn"
                                :class="{ 'cam-toggle-btn--active': discountType === 'percent' }"
                                @click="discountType = 'percent'"
                            >%</button>
                            <button
                                type="button"
                                class="cam-toggle-btn"
                                :class="{ 'cam-toggle-btn--active': discountType === 'fixed' }"
                                @click="discountType = 'fixed'"
                            >R$</button>
                        </div>
                        <div class="admin-modal-input-wrap cam-input-wrap">
                            <span class="admin-modal-floating-label">{{ discountType === 'percent' ? '%' : 'R$' }}</span>
                            <input
                                v-model="discountValue"
                                type="number"
                                min="0"
                                :max="discountType === 'percent' ? 100 : subtotal"
                                step="0.01"
                                :placeholder="discountType === 'percent' ? '0' : '0,00'"
                            />
                        </div>
                    </div>
                </fieldset>

                <!-- tip -->
                <fieldset class="cam-fieldset">
                    <legend class="cam-legend">Gorjeta <span class="cam-hint">(opcional)</span></legend>
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">R$</span>
                        <input
                            v-model="tipValue"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0,00"
                        />
                    </div>
                </fieldset>

                <p v-if="error" class="admin-modal-error">{{ error }}</p>

            </div>

            <div class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="loading" @click="$emit('close')">
                    Cancelar
                </button>
                <button
                    type="button"
                    class="primary"
                    :disabled="!canConfirm"
                    @click="handleConfirm"
                >
                    {{ loading ? 'Aguarde…' : 'Confirmar fechamento' }}
                </button>
            </div>
        </div>
    </div>
</template>
