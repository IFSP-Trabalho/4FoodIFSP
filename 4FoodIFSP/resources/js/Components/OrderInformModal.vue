<script setup>
import axios from 'axios';
import { ref, computed, onMounted } from 'vue';
import { formatPriceBRL } from '../utils/formatPrice.js';

const props = defineProps({
    ticket: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const customerName  = ref(props.ticket.customer_name || '');
const customerPhone = ref(props.ticket.phone_number || '');

const items       = ref([]); // { dish_id, name, price, quantity }
const dishes      = ref([]);
const categories  = ref([]);
const menuLoading = ref(true);
const showMenu    = ref(false);
const search      = ref('');
const submitting  = ref(false);
const errorMsg    = ref(null);

onMounted(async () => {
    try {
        const { data } = await axios.get('/whatsapp/inbox/menu');
        categories.value = data.categories ?? [];
        dishes.value     = data.dishes ?? [];
    } catch (e) {
        errorMsg.value = 'Não foi possível carregar o cardápio.';
    } finally {
        menuLoading.value = false;
    }
});

const groupedMenu = computed(() => {
    const q = search.value.trim().toLowerCase();
    const match = (d) => !q || d.name.toLowerCase().includes(q);

    return categories.value
        .map((cat) => ({
            id: cat.id,
            name: cat.name,
            dishes: dishes.value.filter((d) => d.category_id === cat.id && match(d)),
        }))
        .filter((cat) => cat.dishes.length > 0);
});

const total = computed(() =>
    items.value.reduce((sum, i) => sum + i.price * i.quantity, 0),
);

const canSave = computed(
    () => customerName.value.trim() !== '' && items.value.length > 0 && !submitting.value,
);

function addDish(dish) {
    const existing = items.value.find((i) => i.dish_id === dish.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        items.value.push({ dish_id: dish.id, name: dish.name, price: dish.price, quantity: 1 });
    }
}

function inc(item) {
    item.quantity += 1;
}

function dec(item) {
    if (item.quantity > 1) {
        item.quantity -= 1;
    } else {
        removeItem(item);
    }
}

function removeItem(item) {
    items.value = items.value.filter((i) => i !== item);
}

function close() {
    emit('close');
}

async function save() {
    if (!canSave.value) return;
    submitting.value = true;
    errorMsg.value = null;

    try {
        await axios.post(`/whatsapp/inbox/tickets/${props.ticket.id}/order`, {
            customer_name:  customerName.value.trim(),
            customer_phone: customerPhone.value.trim() || null,
            items: items.value.map((i) => ({ dish_id: i.dish_id, quantity: i.quantity })),
        });
        emit('saved');
        emit('close');
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Erro ao registrar o pedido.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="admin-modal-overlay" @click.self="close" />
    <div class="admin-modal" role="dialog" aria-modal="true">
        <div class="admin-modal-head">
            <h3>Informar pedido</h3>
            <p>Registre o que o cliente pediu neste atendimento.</p>
        </div>

        <form class="admin-modal-form" @submit.prevent="save">
            <div class="admin-modal-body">
                <div class="admin-modal-field">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">Nome *</span>
                        <input v-model="customerName" type="text" maxlength="255" placeholder="Nome do cliente" required />
                    </div>
                </div>

                <div class="admin-modal-field">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">Número</span>
                        <input v-model="customerPhone" type="text" maxlength="40" placeholder="Telefone do cliente" />
                    </div>
                </div>

                <div class="order-section">
                    <div class="order-section-head">
                        <span>Pedido do cliente</span>
                        <button type="button" class="order-add-btn" @click="showMenu = !showMenu">
                            {{ showMenu ? 'Fechar cardápio' : '+ Adicionar prato' }}
                        </button>
                    </div>

                    <p v-if="items.length === 0" class="order-empty">Nenhum prato selecionado.</p>
                    <ul v-else class="order-items">
                        <li v-for="i in items" :key="i.dish_id" class="order-item">
                            <span class="order-item-name">{{ i.name }}</span>
                            <span class="order-item-price">{{ formatPriceBRL(i.price * i.quantity) }}</span>
                            <div class="order-qty">
                                <button type="button" @click="dec(i)">−</button>
                                <span>{{ i.quantity }}</span>
                                <button type="button" @click="inc(i)">+</button>
                            </div>
                            <button type="button" class="order-item-remove" aria-label="Remover" @click="removeItem(i)">×</button>
                        </li>
                    </ul>

                    <div v-if="showMenu" class="order-menu">
                        <input v-model="search" class="order-menu-search" type="search" placeholder="Buscar prato..." />

                        <p v-if="menuLoading" class="order-empty">Carregando cardápio…</p>
                        <p v-else-if="dishes.length === 0" class="order-empty">Nenhum prato cadastrado.</p>
                        <div v-else class="order-menu-list">
                            <template v-for="cat in groupedMenu" :key="cat.id">
                                <p class="order-menu-cat">{{ cat.name }}</p>
                                <button
                                    v-for="d in cat.dishes"
                                    :key="d.id"
                                    type="button"
                                    class="order-menu-dish"
                                    @click="addDish(d)"
                                >
                                    <span class="order-menu-dish-name">{{ d.name }}</span>
                                    <span class="order-menu-dish-price">{{ formatPriceBRL(d.price) }}</span>
                                    <span class="order-menu-dish-add">Adicionar</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div v-if="items.length" class="order-total">
                        <span>Total</span>
                        <strong>{{ formatPriceBRL(total) }}</strong>
                    </div>
                </div>

                <p v-if="errorMsg" class="admin-modal-error">{{ errorMsg }}</p>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="submitting" @click="close">Cancelar</button>
                <button type="submit" class="primary" :disabled="!canSave">
                    {{ submitting ? 'Salvando...' : 'Registrar pedido' }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped src="./styles/OrderInformModal.css"></style>
