# Feature: kitchen-orders

> Contexto: Tela principal da cozinha. Kanban de pedidos ao vivo (Pendente / Preparando / Finalizados) com aba de histórico. A cozinha avança o status dos pedidos via botão de ação.
> Depende de: [auth.md](auth.md), [schema.md](../database/schema.md)
> Roles com acesso: `kitchen` apenas
> Stack: Inertia.js + Vue 3, Laravel controller

---

## Objetivo

Exibir para o operador da cozinha todos os pedidos ativos organizados em três colunas (kanban), permitindo avançar o status de cada pedido com um clique. A aba de histórico exibe pedidos já concluídos ou cancelados com filtro por período.

---

## Layout

### Estrutura geral

```
┌─────────────────────────────────────────────────────────────────┐
│  [sidebar]  │  [topbar]  Pedidos                                 │
│  52px       ├─────────────────────────────────────────────────  │
│  ícones     │  [Pedidos Ao vivo]  [Histórico de pedidos]         │
│             │  [input: Pesquisar Prato ...]       [🔔]           │
│             ├─────────────────────────────────────────────────  │
│             │  Pendente 2  │  Preparando 1  │  Finalizados 1     │
│             │  [card]      │  [card]        │  [card verde]      │
│             │  [card]      │                │                    │
└─────────────────────────────────────────────────────────────────┘
```

### Sidebar (`AppSidebar.vue` — componente compartilhado)

- Mesmo componente do admin
- Item ativo: `pedidos`

### Topbar (`AppTopbar.vue` — componente compartilhado)

- Título: `"Pedidos"`
- Badge do role: `Cozinha`

### Barra de ação (abaixo do topbar)

- Tabs: `[Pedidos Ao vivo]` | `[Histórico de pedidos]`
- Input de busca: placeholder `"Pesquisar Prato"` — filtra cards por nome de item (client-side)
- Ícone de sino à direita — reservado para notificações (futuro, sem ação no MVP)

---

## Aba: Pedidos Ao vivo

### Kanban — 3 colunas

| Coluna | `orders.status` | Badge count | Cor da coluna |
|---|---|---|---|
| Pendente | `pending` | sim | label padrão |
| Preparando | `in_progress` | sim | label padrão |
| Finalizados | `ready` (do dia) | sim | label padrão |

Pedidos com `status = ready | cancelled` saem da view ao-vivo ao final do dia e aparecem exclusivamente no Histórico.

---

## Card de pedido (`OrderCard.vue`)

### Campos exibidos

| Campo | Fonte | Nota |
|---|---|---|
| ID do pedido | `orders.id` (formatado como `#ped-XXXX`) | exibido no topo esquerdo |
| Tempo decorrido | `orders.created_at` | **desativado — fase 2** |
| Origem (Local / Delivery) | `orders.origin` | **desativado — fase 2** |
| Mesa | `tables.label` ou `Mesa {number}` | ex: `Mesa 12` |
| Itens | `order_items.quantity` + `dishes.name` | ex: `1x Bacon salada`, `2x Coca-cola` |
| Observação | `order_items.note` (concatenado) | ex: `– Sem salada e coca zero` |
| Botão de ação | varia por status | ver tabela abaixo |

### Botão de ação por status

| Status | Label | Comportamento | Estilo |
|---|---|---|---|
| `pending` | Preparar pedido | `PATCH` → `in_progress` | fundo `#1a1a1a`, texto branco |
| `in_progress` | Finalizar Pedido | `PATCH` → `ready` | fundo `#1D9E75`, texto branco |
| `ready` | Pedido finalizado | desabilitado | fundo `#e0e0e0`, texto `#aaa`, `cursor: default` |

### Estilo visual por status

| Status | Borda esquerda | Fundo do card | Badge extra |
|---|---|---|---|
| `pending` | `3px solid #D85A30` (vermelho) | branco | — |
| `in_progress` | `3px solid #EF9F27` (âmbar) | branco | — |
| `ready` | `3px solid #1D9E75` (verde) | `#E1F5EE` | `FEITO` (badge verde `#1D9E75`) |

Nos cards `ready`: todos os itens e a observação são exibidos com `text-decoration: line-through` e opacidade reduzida.

---

## Aba: Histórico de pedidos

Tabela com todos os pedidos com `status = ready | cancelled`, ordenados por `updated_at DESC`.

### Filtro de data

- Dois inputs `date` lado a lado: `De` (`date_from`) e `Até` (`date_to`)
- Botão `[Filtrar]` — dispara `GET /kitchen/orders/history?date_from=&date_to=`
- Padrão ao abrir a aba: data de hoje

### Colunas da tabela

| Coluna | Fonte |
|---|---|
| Pedido | `#ped-XXXX` |
| Mesa | `Mesa {number}` |
| Itens | nomes dos pratos separados por vírgula |
| Status | badge `Pronto` (verde) ou `Cancelado` (cinza) |
| Horário | `updated_at` formatado `HH:mm` |

---

## Dados estáticos (MVP)

O fluxo do cliente (tablet) ainda não está implementado. Para o MVP, os pedidos são hardcoded no controller espelhando o Figma de referência.

```php
// Kitchen\OrdersController@index
private function getOrders(): array
{
    return [
        'pending' => [
            [
                'id'    => 'ped-1221',
                'mesa'  => 'Mesa 12',
                'items' => [
                    ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                    ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                ],
                'note_summary' => '– Sem salada e coca zero',
            ],
            [
                'id'    => 'ped-1222',
                'mesa'  => 'Mesa 12',
                'items' => [
                    ['qty' => 1, 'name' => 'Bacon salada', 'note' => null],
                    ['qty' => 2, 'name' => 'Coca-cola',    'note' => null],
                ],
                'note_summary' => null,
            ],
        ],
        'in_progress' => [
            [
                'id'    => 'ped-1220',
                'mesa'  => 'Mesa 12',
                'items' => [
                    ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                    ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                ],
                'note_summary' => '– Sem salada e coca zero',
            ],
        ],
        'ready' => [
            [
                'id'    => 'ped-1219',
                'mesa'  => 'Mesa 12',
                'items' => [
                    ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                    ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                ],
                'note_summary' => '– Sem salada e coca zero',
            ],
        ],
    ];
}
```

> Quando as queries reais forem implementadas, substituir apenas os valores — a estrutura do array deve permanecer idêntica para não quebrar o componente Vue.

---

## Fluxo de status

```
pending → in_progress → ready
                      ↘ cancelled  - inserir 3 pontos no card -> cancelar pedido -> aviso se deseja cancelar -> motivo - fase2
```

Cada clique no botão de ação do card dispara um `PATCH` que avança o status. A UI atualiza via Inertia reload da prop `orders`.

---

## Rotas

```php
// routes/web.php
Route::middleware(['firebase.auth', 'role:kitchen'])->prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/orders',         [OrdersController::class, 'index'])->name('orders');
    Route::get('/orders/history', [OrdersController::class, 'history'])->name('orders.history');
    Route::patch('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.updateStatus');
});
```

| Método | URI | Controller@method | Middleware |
|---|---|---|---|
| GET | `/kitchen/orders` | `Kitchen\OrdersController@index` | `firebase.auth`, `role:kitchen` |
| GET | `/kitchen/orders/history` | `Kitchen\OrdersController@history` | `firebase.auth`, `role:kitchen` |
| PATCH | `/kitchen/orders/{order}/status` | `Kitchen\OrdersController@updateStatus` | `firebase.auth`, `role:kitchen` |

---

## Implementação — backend

```php
<?php
// app/Http/Controllers/Kitchen/OrdersController.php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Kitchen/Orders', [
            'orders' => $this->getOrders(), // dados estáticos por ora
        ]);
    }

    public function history(): Response
    {
        // MVP: retorna lista vazia; implementar query real na fase 2
        return Inertia::render('Kitchen/Orders', [
            'orders'  => $this->getOrders(),
            'history' => [],
        ]);
    }

    public function updateStatus($order): \Illuminate\Http\RedirectResponse
    {
        // MVP: sem persistência real; redireciona de volta
        // Fase 2: Order::findOrFail($order)->advanceStatus();
        return redirect()->back();
    }

    private function getOrders(): array
    {
        return [
            'pending' => [
                [
                    'id'           => 'ped-1221',
                    'mesa'         => 'Mesa 12',
                    'items'        => [
                        ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                        ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                    ],
                    'note_summary' => '– Sem salada e coca zero',
                ],
                [
                    'id'           => 'ped-1222',
                    'mesa'         => 'Mesa 12',
                    'items'        => [
                        ['qty' => 1, 'name' => 'Bacon salada', 'note' => null],
                        ['qty' => 2, 'name' => 'Coca-cola',    'note' => null],
                    ],
                    'note_summary' => null,
                ],
            ],
            'in_progress' => [
                [
                    'id'           => 'ped-1220',
                    'mesa'         => 'Mesa 12',
                    'items'        => [
                        ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                        ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                    ],
                    'note_summary' => '– Sem salada e coca zero',
                ],
            ],
            'ready' => [
                [
                    'id'           => 'ped-1219',
                    'mesa'         => 'Mesa 12',
                    'items'        => [
                        ['qty' => 1, 'name' => 'Bacon salada', 'note' => 'Sem salada'],
                        ['qty' => 2, 'name' => 'Coca-cola',    'note' => 'coca zero'],
                    ],
                    'note_summary' => '– Sem salada e coca zero',
                ],
            ],
        ];
    }
}
```

---

## Implementação — frontend

### Estrutura de arquivos

```
resources/js/
  Pages/
    Kitchen/
      Orders.vue          ← página principal (tabs + kanban + histórico)
  Components/
    OrderCard.vue         ← card reutilizável por status
```

---

### `Pages/Kitchen/Orders.vue` — estrutura

```vue
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import OrderCard from '../../Components/OrderCard.vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
        // { pending: [...], in_progress: [...], ready: [...] }
    },
    history: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('live'); // 'live' | 'history'
const search = ref('');

function filterBySearch(list) {
    if (!search.value) return list;
    return list.filter(order =>
        order.items.some(item =>
            item.name.toLowerCase().includes(search.value.toLowerCase())
        )
    );
}

const pendente    = computed(() => filterBySearch(props.orders.pending     ?? []));
const preparando  = computed(() => filterBySearch(props.orders.in_progress ?? []));
const finalizados = computed(() => filterBySearch(props.orders.ready       ?? []));

function advanceStatus(orderId) {
    router.patch(`/kitchen/orders/${orderId}/status`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
  <div class="shell">
    <AppSidebar active="pedidos" />
    <div class="main">
      <AppTopbar title="Pedidos" role-badge="Cozinha" />
      <div class="content">

        <!-- Tabs + busca -->
        <div class="orders-toolbar">
          <div class="tabs">
            <button :class="{ active: activeTab === 'live' }"    @click="activeTab = 'live'">Pedidos Ao vivo</button>
            <button :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">Histórico de pedidos</button>
          </div>
          <div class="toolbar-right">
            <input v-model="search" type="text" placeholder="Pesquisar Prato" class="search-input" />
            <button class="bell-btn" disabled>🔔</button>
          </div>
        </div>

        <!-- Aba ao vivo -->
        <div v-if="activeTab === 'live'" class="kanban">
          <div class="kanban-col">
            <div class="col-header">
              <span class="col-title">Pendente</span>
              <span class="col-badge">{{ pendente.length }}</span>
            </div>
            <OrderCard
              v-for="order in pendente"
              :key="order.id"
              :order="order"
              status="pending"
              @advance="advanceStatus"
            />
          </div>

          <div class="kanban-col">
            <div class="col-header">
              <span class="col-title">Preparando</span>
              <span class="col-badge">{{ preparando.length }}</span>
            </div>
            <OrderCard
              v-for="order in preparando"
              :key="order.id"
              :order="order"
              status="in_progress"
              @advance="advanceStatus"
            />
          </div>

          <div class="kanban-col">
            <div class="col-header">
              <span class="col-title">Finalizados</span>
              <span class="col-badge">{{ finalizados.length }}</span>
            </div>
            <OrderCard
              v-for="order in finalizados"
              :key="order.id"
              :order="order"
              status="ready"
              @advance="advanceStatus"
            />
          </div>
        </div>

        <!-- Aba histórico -->
        <div v-if="activeTab === 'history'" class="history-view">
          <!-- implementar tabela com filtro de data na fase 2 -->
          <p class="empty-state">Histórico disponível na próxima fase.</p>
        </div>

      </div>
    </div>
  </div>
</template>
```

---

### `Components/OrderCard.vue` — estrutura

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
        // { id, mesa, items: [{ qty, name, note }], note_summary }
    },
    status: {
        type: String,
        required: true,
        // 'pending' | 'in_progress' | 'ready'
    },
});

const emit = defineEmits(['advance']);

const actionLabel = computed(() => ({
    pending:     'Preparar pedido',
    in_progress: 'Finalizar Pedido',
    ready:       'Pedido finalizado',
}[props.status]));

const actionDisabled = computed(() => props.status === 'ready');

const cardClass = computed(() => ({
    'card--pending':     props.status === 'pending',
    'card--in-progress': props.status === 'in_progress',
    'card--ready':       props.status === 'ready',
}));

const itemClass = computed(() => props.status === 'ready' ? 'item--done' : '');
</script>

<template>
  <div class="order-card" :class="cardClass">
    <div class="card-head">
      <span class="order-id">#{{ order.id }}</span>
      <span v-if="status === 'ready'" class="badge-feito">FEITO</span>
    </div>

    <p class="mesa-label">{{ order.mesa }}</p>

    <ul class="items-list">
      <li
        v-for="item in order.items"
        :key="item.name"
        class="item-row"
        :class="itemClass"
      >
        <span class="item-qty">{{ item.qty }}x</span>
        <span class="item-name">{{ item.name }}</span>
      </li>
    </ul>

    <p v-if="order.note_summary" class="note" :class="itemClass">
      {{ order.note_summary }}
    </p>

    <button
      class="action-btn"
      :class="`action-btn--${status}`"
      :disabled="actionDisabled"
      @click="!actionDisabled && emit('advance', order.id)"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>
```

---

## Design tokens para esta tela

| Token | Valor |
|---|---|
| Shell / fundo | `#f6f7f9` |
| Sidebar width | `52px` |
| Topbar height | `52px` |
| Fonte mono (IDs) | `DM Mono` ou `JetBrains Mono` |
| Section label | `11px / 500 / uppercase / letter-spacing 0.06em` |

### Cores por status de card

| Status | Borda esquerda | Fundo | Badge/Botão |
|---|---|---|---|
| `pending` | `#D85A30` (coral) | `#ffffff` | botão `#1a1a1a` |
| `in_progress` | `#EF9F27` (âmbar) | `#ffffff` | botão `#1D9E75` |
| `ready` | `#1D9E75` (verde) | `#E1F5EE` | badge `FEITO` `#1D9E75`, botão disabled |

### CSS resumido (scoped no `OrderCard.vue`)

```css
.order-card        { background: #fff; border-radius: 8px; border-left: 3px solid transparent; padding: 14px; margin-bottom: 12px; }
.card--pending     { border-left-color: #D85A30; }
.card--in-progress { border-left-color: #EF9F27; }
.card--ready       { border-left-color: #1D9E75; background: #E1F5EE; }

.badge-feito       { background: #1D9E75; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
.item--done        { text-decoration: line-through; opacity: 0.5; }

.action-btn        { width: 100%; padding: 10px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; margin-top: 12px; }
.action-btn--pending     { background: #1a1a1a; color: #fff; }
.action-btn--in_progress { background: #1D9E75; color: #fff; }
.action-btn--ready       { background: #e0e0e0; color: #aaa; cursor: default; }
```

---

## Eventos real-time (documentados — fase 2)

| Evento Laravel | Canal | Efeito na UI |
|---|---|---|
| `OrderCreated` | `kitchen` | Novo card aparece na coluna Pendente sem refresh |
| `OrderStatusUpdated` | `kitchen` | Card migra para a coluna correspondente ao novo status |

Implementado via **Laravel Broadcasting** + **Laravel Echo** no frontend (Pusher ou Reverb).

---

## O que NÃO está nesta feature (fase 2)

- Tempo decorrido por pedido (campo de duração no card)
- Badge Local / iFood / Delivery no card
- Notificações (sino)
- Real-time via WebSocket
- Persistência real: o `updateStatus` atual apenas redireciona; a query no banco (`Order::findOrFail(...)->update(['status' => ...])`) é da fase 2
- Histórico com filtro de data (tabela completa)

---

## Status de implementação

- [ ] Rota `GET /kitchen/orders` com middleware `role:kitchen`
- [ ] Rota `GET /kitchen/orders/history` com middleware `role:kitchen`
- [ ] Rota `PATCH /kitchen/orders/{order}/status` com middleware `role:kitchen`
- [ ] `Kitchen\OrdersController@index` com dados estáticos
- [ ] `Kitchen\OrdersController@updateStatus` (redireciona, sem persistência real no MVP)
- [ ] `Pages/Kitchen/Orders.vue` com tabs + kanban 3 colunas
- [ ] `Components/OrderCard.vue` com botão de ação dinâmico e estilo por status
- [ ] Busca por prato (filtro client-side via `computed`)
- [ ] Aba Histórico com tabela e filtro de data (fase 2)
- [ ] Real-time via Broadcasting + Echo (fase 2)
- [ ] Persistência real de status no banco (fase 2)
