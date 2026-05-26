<script setup>
import './styles/Order.css';
import { ref, computed } from 'vue';
import axios from 'axios';
import TabletLayout from '../../Layouts/TabletLayout.vue';
import DishCategoryChip from '../../Components/DishCategoryChip.vue';
import TabletDishCard from '../../Components/TabletDishCard.vue';
import TabletCart from '../../Components/TabletCart.vue';
import TabletItemNoteModal from '../../Components/TabletItemNoteModal.vue';
import { formatPriceBRL } from '../../utils/formatPrice.js';

const props = defineProps({
    mesa:       { type: Number, required: true },
    session_id: { type: String, default: null },
    categories: { type: Array,  required: true },
    dishes:     { type: Array,  required: true },
});

const cart = ref([]);
const activeCategory = ref(null);
const drawerOpen = ref(false);
const confirmModal = ref(false);
const successMessage = ref('');
const orderError = ref('');
const isSubmitting = ref(false);
const noteModal = ref({ open: false, dishId: null, value: '' });

const filteredDishes = computed(() =>
    activeCategory.value
        ? props.dishes.filter((d) => d.category_id === activeCategory.value)
        : props.dishes
);

const cartTotal = computed(() =>
    cart.value.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0)
);

const cartCount = computed(() =>
    cart.value.reduce((sum, line) => sum + line.quantity, 0)
);

function getCartLine(dishId) {
    return cart.value.find((l) => l.dishId === dishId);
}

function addToCart(dish) {
    const existing = getCartLine(dish.id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({
            dishId: dish.id,
            name: dish.name,
            unitPrice: dish.price,
            quantity: 1,
            note: '',
        });
    }
}

function increment(dishId) {
    const line = getCartLine(dishId);
    if (line) line.quantity++;
}

function decrement(dishId) {
    const idx = cart.value.findIndex((l) => l.dishId === dishId);
    if (idx === -1) return;
    if (cart.value[idx].quantity === 1) {
        cart.value.splice(idx, 1);
    } else {
        cart.value[idx].quantity--;
    }
}

function openNoteModal(dishId) {
    const line = getCartLine(dishId);
    noteModal.value = { open: true, dishId, value: line ? line.note : '' };
}

function saveNote(value) {
    const line = getCartLine(noteModal.value.dishId);
    if (line) line.note = value;
    noteModal.value.open = false;
}

async function confirmOrder() {
    if (cart.value.length === 0) return;

    isSubmitting.value = true;
    orderError.value = '';

    try {
        await axios.post('/tablet/orders', {
            mesa: props.mesa,
            items: cart.value.map((line) => ({
                dish_id:  line.dishId,
                quantity: line.quantity,
                note:     line.note?.trim() || null,
            })),
        });

        cart.value = [];
        confirmModal.value = false;
        drawerOpen.value = false;
        successMessage.value = 'Pedido enviado para a cozinha';
        setTimeout(() => { successMessage.value = ''; }, 5000);
    } catch (err) {
        if (err.response?.status === 422) {
            orderError.value = err.response.data?.message ?? 'Dados inválidos.';
        } else {
            orderError.value = 'Erro de conexão. Tente novamente.';
        }
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <TabletLayout :mesa="mesa">
        <div class="order-shell">
            <section class="order-menu" aria-label="Cardápio">
                <div class="order-chips-row" role="list" aria-label="Filtrar por categoria">
                    <DishCategoryChip
                        name="Todos"
                        slug="all"
                        :dishes-count="dishes.length"
                        :active="activeCategory === null"
                        @select="activeCategory = null"
                    />
                    <DishCategoryChip
                        v-for="cat in categories"
                        :key="cat.id"
                        :name="cat.name"
                        :slug="cat.slug"
                        :dishes-count="cat.dishes_count"
                        :active="activeCategory === cat.id"
                        @select="activeCategory = cat.id"
                    />
                </div>

                <div class="order-dish-grid">
                    <div
                        v-if="filteredDishes.length === 0"
                        class="order-dish-empty"
                        role="status"
                    >
                        <p class="order-dish-empty-title">Nenhum item disponível no momento.</p>
                        <p class="order-dish-empty-sub">O cardápio será atualizado em breve.</p>
                    </div>
                    <template v-else>
                        <TabletDishCard
                            v-for="dish in filteredDishes"
                            :key="dish.id"
                            :dish="dish"
                            :cart-quantity="getCartLine(dish.id)?.quantity ?? 0"
                            @add="addToCart(dish)"
                            @increment="increment(dish.id)"
                            @decrement="decrement(dish.id)"
                        />
                    </template>
                </div>
            </section>

            <aside class="order-cart-sidebar" aria-label="Seu pedido">
                <p class="order-cart-sidebar-title">Seu pedido</p>
                <TabletCart
                    :cart="cart"
                    :total="cartTotal"
                    @increment="increment"
                    @decrement="decrement"
                    @edit-note="openNoteModal"
                    @confirm="confirmModal = true"
                />
            </aside>
        </div>

        <button
            class="order-fab"
            aria-label="Ver pedido"
            @click="drawerOpen = true"
        >
            Ver pedido ({{ cartCount }})
        </button>

        <Transition name="drawer">
            <div
                v-if="drawerOpen"
                class="order-drawer-overlay"
                role="dialog"
                aria-label="Carrinho"
                @click.self="drawerOpen = false"
            >
                <div class="order-drawer">
                    <div class="order-drawer-header">
                        <span class="order-drawer-title">Seu pedido</span>
                        <button
                            class="order-drawer-close"
                            aria-label="Fechar carrinho"
                            @click="drawerOpen = false"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <TabletCart
                        :cart="cart"
                        :total="cartTotal"
                        @increment="increment"
                        @decrement="decrement"
                        @edit-note="openNoteModal"
                        @confirm="confirmModal = true"
                    />
                </div>
            </div>
        </Transition>

        <TabletItemNoteModal
            v-if="noteModal.open"
            :initial-value="noteModal.value"
            @save="saveNote"
            @close="noteModal.open = false"
        />

        <Transition name="fade">
            <div
                v-if="confirmModal"
                class="order-confirm-overlay"
                @click.self="confirmModal = false"
            >
                <div class="order-confirm-modal" role="dialog" aria-labelledby="confirm-title">
                    <h2 id="confirm-title" class="order-confirm-title">Confirmar pedido</h2>
                    <p class="order-confirm-meta">
                        Mesa {{ mesa }}&nbsp;&nbsp;·&nbsp;&nbsp;{{ cartCount }}
                        {{ cartCount === 1 ? 'item' : 'itens' }}
                    </p>
                    <p class="order-confirm-total">{{ formatPriceBRL(cartTotal) }}</p>
                    <p v-if="orderError" class="order-confirm-error">{{ orderError }}</p>
                    <div class="order-confirm-actions">
                        <button class="order-confirm-cancel" :disabled="isSubmitting" @click="confirmModal = false">Cancelar</button>
                        <button class="order-confirm-send" :disabled="isSubmitting" @click="confirmOrder">
                            {{ isSubmitting ? 'Enviando...' : 'Enviar para a cozinha' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="successMessage" class="order-success-toast" role="status" aria-live="polite">
                {{ successMessage }}
            </div>
        </Transition>
    </TabletLayout>
</template>
