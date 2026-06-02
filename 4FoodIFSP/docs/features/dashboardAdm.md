# Feature: admin-dashboard

> Contexto: Primeira tela que o admin vê após login. Resumo multi-departamento com métricas estáticas (prontas para substituição por queries reais).  
> Depende de: [auth.md](auth.md) (redirecionamento pós-login), [schema.md](../database/schema.md)  
> Roles com acesso: `admin` apenas  
> Stack: Inertia.js + Vue 3, Laravel controller

---

## Objetivo

Exibir ao administrador uma visão consolidada de todos os departamentos em uma única tela: KPIs do dia, status de cada departamento e os últimos pedidos registrados.

---

## Layout

### Estrutura geral

```
┌─────────────────────────────────────────────┐
│  [sidebar]  │  [topbar]                      │
│  52px       │  Visão geral — hoje, DD/MM     │
│  ícones     ├────────────────────────────────│
│  apenas     │  KPIs (4 cards)                │
│             │  Departamentos (2x2 grid)      │
│             │  Últimos pedidos (tabela)      │
└─────────────────────────────────────────────┘
```

### Sidebar (componente separado `AppSidebar.vue`)

- Largura fixa: `52px`
- Ícones apenas — sem labels
- Avatar do usuário no topo (iniciais ou ícone genérico)
- Item ativo com fundo `#FAECE7` e ícone `#993C1D` (coral)
- Separadores (`hr`) agrupando seções
- Itens (em ordem):
  1. Avatar / perfil
  2. Início (home)
  3. Dashboard ← ativo nesta tela
  4. `---`
  5. Pedidos
  6. Mesas
  7. Cadastros
  8. `---`
  9. Financeiro
  10. Relatórios

> Nota: itens sem rota implementada recebem `disabled` visual (ícone com opacidade 40%) e `cursor: default`. Não usar `<a>` — usar `<button>` ou `<div>` com `@click` que verifica se rota existe.

### Topbar

- Altura: `52px`
- Dot verde indicando sistema ativo
- Título: `"Visão geral"`
- Subtítulo: data atual formatada
- Badge do role do usuário (`Admin`) alinhado à direita

---

## Seções do dashboard

### 1. KPIs do dia (4 cards em grid)

| Métrica | Campo no banco | Cálculo |
|---|---|---|
| Faturamento | `order_items.unit_price * quantity` | `SUM` onde `orders.paid = true` e `DATE(created_at) = hoje` |
| Pedidos totais | `orders` | `COUNT` onde `DATE(created_at) = hoje` |
| Mesas abertas | `orders` | `COUNT DISTINCT table_id` onde `paid = false` e `origin = table` |
| Ticket médio | calculado | `faturamento / COUNT(pedidos pagos)` |

Cada card contém:
- Label em fonte mono, 11px, cor terciária
- Valor principal em fonte mono, 22px, peso 500
- Delta comparativo (ontem) — verde se positivo, âmbar se alerta

### 2. Cards de departamento (grid 2×2)

Um card por departamento. Cada card mostra:
- Dot colorado + nome + badge de status rápido
- 4 linhas de métricas com mini-barra de progresso relativa

#### Cozinha
| Métrica | Campo | Cálculo |
|---|---|---|
| Em preparo | `orders.status` | `COUNT` onde `status = 'in_progress'` |
| Prontos hoje | `orders.status` | `COUNT` onde `status = 'ready'` e hoje |
| Tempo médio | `orders` | `AVG(updated_at - created_at)` onde `status = 'ready'` hoje |
| Cancelados | `orders.status` | `COUNT` onde `status = 'cancelled'` e hoje |

#### Financeiro / Caixa
| Métrica | Campo | Cálculo |
|---|---|---|
| Mesas abertas | `orders` | `COUNT DISTINCT table_id` onde `paid = false` |
| Mesas fechadas | `orders` | `COUNT DISTINCT table_id` onde `paid = true` hoje |
| Faturamento | `order_items` | `SUM(unit_price * quantity)` onde `paid = true` hoje |
| Pendente (aberto) | `order_items` | `SUM` onde `paid = false` e `status != 'cancelled'` |

#### Garçom
| Métrica | Campo | Cálculo |
|---|---|---|
| Mesas atendidas | `orders` | `COUNT DISTINCT table_id` onde garçom operou hoje |
| Contas fechadas | `orders` | `COUNT` de fechamentos feitos por `waiter` hoje |
| Último fechamento | `orders` | `MAX(updated_at)` de fechamentos do garçom |
| Pedidos atendidos | `orders` | `COUNT` dos pedidos de mesas ativas do garçom |

#### WhatsApp
| Métrica | Campo | Cálculo |
|---|---|---|
| Triagem | `wa_tickets.status` | `COUNT` onde `status = 'triage'` |
| Em andamento | `wa_tickets.status` | `COUNT` onde `status = 'in_progress'` |
| Fechados hoje | `wa_tickets.status` | `COUNT` onde `status = 'closed'` e hoje |
| Pedidos delivery | `orders.origin` | `COUNT` onde `origin = 'delivery'` e hoje |

### 3. Últimos pedidos (lista)

Últimos 10 pedidos ordenados por `created_at DESC`.

Colunas por linha:
- Mesa (ex: `Mesa 3` ou `Delivery`)
- Itens (lista simplificada: nome dos pratos separados por vírgula)
- Status badge (Em preparo / Pronto / Cancelado)
- Valor total da ordem (`SUM(unit_price * quantity)`)

---

## Dados estáticos (MVP)

Enquanto as queries não estiverem implementadas, usar dados hardcoded no controller:

```php
// Admin\DashboardController@index
$stats = [
    'faturamento'    => 'R$1.840',
    'pedidos_totais' => 47,
    'mesas_abertas'  => 6,
    'ticket_medio'   => 'R$39',

    'cozinha' => [
        'em_preparo' => 8,
        'prontos'    => 31,
        'tempo_medio'=> '14 min',
        'cancelados' => 2,
    ],

    'financeiro' => [
        'mesas_abertas'  => 6,
        'mesas_fechadas' => 4,
        'faturamento'    => 'R$1.840',
        'pendente'       => 'R$412',
    ],

    'garcom' => [
        'mesas_atendidas'  => 3,
        'contas_fechadas'  => 2,
        'ultimo_fechamento'=> '13:42',
        'pedidos_atendidos'=> 19,
    ],

    'whatsapp' => [
        'triagem'         => 2,
        'em_andamento'    => 3,
        'fechados_hoje'   => 11,
        'pedidos_delivery'=> 6,
    ],

    'ultimos_pedidos' => [
        ['mesa' => 'Mesa 3',   'itens' => 'X-Burger, Batata, Refrigerante', 'status' => 'in_progress', 'total' => 'R$52'],
        ['mesa' => 'Mesa 7',   'itens' => 'Frango grelhado, Suco',          'status' => 'ready',       'total' => 'R$38'],
        ['mesa' => 'Delivery', 'itens' => 'Pizza calabresa, Refrigerante',  'status' => 'in_progress', 'total' => 'R$61'],
        ['mesa' => 'Mesa 1',   'itens' => 'Salada caesar, Água',            'status' => 'cancelled',   'total' => 'R$29'],
    ],
];
```

> Quando as queries reais forem implementadas, substituir apenas os valores — a estrutura do array deve permanecer idêntica para não quebrar o componente Vue.

---

## Rotas

| Método | URI | Controller@method | Middleware |
|---|---|---|---|
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | `firebase.auth`, `role:admin` |

```php
// routes/web.php
Route::middleware(['firebase.auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
```

---

## Implementação — backend

```php
<?php
// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = $this->getStats(); // dados estáticos por ora

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'date'  => now()->translatedFormat('d \d\e F \d\e Y'),
        ]);
    }

    private function getStats(): array
    {
        return [
            'faturamento'    => 'R$1.840',
            'pedidos_totais' => 47,
            'mesas_abertas'  => 6,
            'ticket_medio'   => 'R$39',
            'cozinha'        => ['em_preparo' => 8, 'prontos' => 31, 'tempo_medio' => '14 min', 'cancelados' => 2],
            'financeiro'     => ['mesas_abertas' => 6, 'mesas_fechadas' => 4, 'faturamento' => 'R$1.840', 'pendente' => 'R$412'],
            'garcom'         => ['mesas_atendidas' => 3, 'contas_fechadas' => 2, 'ultimo_fechamento' => '13:42', 'pedidos_atendidos' => 19],
            'whatsapp'       => ['triagem' => 2, 'em_andamento' => 3, 'fechados_hoje' => 11, 'pedidos_delivery' => 6],
            'ultimos_pedidos'=> [
                ['mesa' => 'Mesa 3',   'itens' => 'X-Burger, Batata, Refrigerante', 'status' => 'in_progress', 'total' => 'R$52'],
                ['mesa' => 'Mesa 7',   'itens' => 'Frango grelhado, Suco',          'status' => 'ready',       'total' => 'R$38'],
                ['mesa' => 'Delivery', 'itens' => 'Pizza calabresa, Refrigerante',  'status' => 'in_progress', 'total' => 'R$61'],
                ['mesa' => 'Mesa 1',   'itens' => 'Salada caesar, Água',            'status' => 'cancelled',   'total' => 'R$29'],
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
    Admin/
      Dashboard.vue        ← página principal
  Components/
    AppSidebar.vue          ← sidebar de ícones (shared)
    AppTopbar.vue           ← topbar (shared)
    KpiCard.vue             ← card de métrica
    DeptCard.vue            ← card de departamento
    OrderRow.vue            ← linha da tabela de pedidos
```

### `Pages/Admin/Dashboard.vue` — estrutura

```vue
<script setup>
defineProps({
  stats: Object,
  date: String,
})

const statusLabel = {
  in_progress: 'Em preparo',
  ready: 'Pronto',
  cancelled: 'Cancelado',
}

const statusClass = {
  in_progress: 'badge-amber',
  ready: 'badge-green',
  cancelled: 'badge-gray',
}
</script>

<template>
  <div class="shell">
    <AppSidebar active="dashboard" />
    <div class="main">
      <AppTopbar title="Visão geral" :subtitle="date" role-badge="Admin" />
      <div class="content">

        <!-- KPIs -->
        <section>
          <p class="section-label">resumo do dia</p>
          <div class="kpi-grid">
            <KpiCard label="faturamento"    :value="stats.faturamento"    delta="↑ 12% vs ontem" delta-type="up" />
            <KpiCard label="pedidos totais" :value="stats.pedidos_totais" delta="↑ 8 vs ontem"   delta-type="up" />
            <KpiCard label="mesas abertas"  :value="stats.mesas_abertas"  delta="3 há +40 min"   delta-type="warn" />
            <KpiCard label="ticket médio"   :value="stats.ticket_medio"   delta="↑ R$3 vs ontem" delta-type="up" />
          </div>
        </section>

        <!-- Departamentos -->
        <section>
          <p class="section-label">departamentos</p>
          <div class="dept-grid">
            <DeptCard name="Cozinha" color="#EF9F27" badge-text="`${stats.cozinha.em_preparo} em preparo`" :rows="[
              { label: 'Em preparo',   value: stats.cozinha.em_preparo, bar: true, max: 10 },
              { label: 'Prontos hoje', value: stats.cozinha.prontos,    bar: true, max: 50 },
              { label: 'Tempo médio',  value: stats.cozinha.tempo_medio },
              { label: 'Cancelados',   value: stats.cozinha.cancelados },
            ]" />
            <!-- repetir para financeiro, garcom, whatsapp -->
          </div>
        </section>

        <!-- Últimos pedidos -->
        <section>
          <div class="orders-list">
            <div class="orders-head">
              <span>Últimos pedidos</span>
              <button @click="$inertia.visit('/admin/orders')">ver todos →</button>
            </div>
            <OrderRow
              v-for="order in stats.ultimos_pedidos"
              :key="order.mesa + order.itens"
              :mesa="order.mesa"
              :itens="order.itens"
              :status="statusLabel[order.status]"
              :status-class="statusClass[order.status]"
              :total="order.total"
            />
          </div>
        </section>

      </div>
    </div>
  </div>
</template>
```

---

## Design tokens para esta tela

| Token | Valor |
|---|---|
| Sidebar width | `52px` |
| Sidebar bg | `var(--color-background-primary)` |
| Active nav bg | `#FAECE7` |
| Active nav icon | `#993C1D` |
| Topbar height | `52px` |
| Dot ativo | `#1D9E75` |
| Font mono (valores) | `DM Mono` ou `JetBrains Mono` |
| KPI value size | `22px / 500` |
| Section label | `11px / 500 / uppercase / letter-spacing 0.06em` |

### Cores por departamento

| Departamento | Dot | Badge bg | Badge text |
|---|---|---|---|
| Cozinha | `#EF9F27` | `#FAEEDA` | `#633806` |
| Financeiro | `#1D9E75` | `#E1F5EE` | `#085041` |
| Garçom | `#D85A30` | `#FAECE7` | `#712B13` |
| WhatsApp | `#378ADD` | `#E6F1FB` | `#0C447C` |

---

## O que NÃO está nessa feature

- Conteúdo das sub-páginas (pedidos, mesas, cadastros) → features separadas
- Dados reais via queries → implementar quando as features de domínio estiverem prontas
- Dashboard de outros roles (kitchen, finance, waiter) → features separadas

---

## Status de implementação

- [ ] Rota `/admin/dashboard` criada com middleware `role:admin`
- [ ] `Admin\DashboardController` criado com dados estáticos
- [ ] `Pages/Admin/Dashboard.vue` criado
- [ ] `Components/AppSidebar.vue` criado (ícones + estado ativo)
- [ ] `Components/AppTopbar.vue` criado
- [ ] `Components/KpiCard.vue` criado
- [ ] `Components/DeptCard.vue` criado
- [ ] `Components/OrderRow.vue` criado
- [ ] Navegação da sidebar sem rota implementada desabilitada visualmente