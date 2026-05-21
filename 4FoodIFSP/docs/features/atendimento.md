# Feature: whatsapp-inbox

> Contexto: Página inicial do módulo de atendimento WhatsApp. O agente visualiza chamados (tickets) organizados por status, filtra por nome e seleciona um contato na lista. Não inclui chat, envio de mensagens nem integração com API.
> Depende de: `features/auth.md`, `database/schema.md` (tabelas `wa_tickets`, `wa_messages`)
> Roles com acesso: `whatsapp_agent` apenas
> Stack: Inertia.js + Vue 3, Laravel controller

---

## Objetivo

Exibir para o agente de atendimento (`whatsapp_agent`) a **inbox de chamados** vindos do WhatsApp: abas por status, lista de contatos com preview da última mensagem e campo de busca por nome. Nesta fase a tela é **somente leitura** — dados mock no controller, sem webhook e sem navegação para tela de conversa.

---

## Escopo desta feature (MVP)

- [ ] Página inbox com shell padrão (sidebar + topbar)
- [ ] Abas: `Em andamento` | `Triagem` | `Fechados` (nessa ordem)
- [ ] Lista de contatos filtrada pela aba ativa
- [ ] Input de filtro por nome (client-side)
- [ ] Badge de contagem por aba
- [ ] Badge `Novo` nos contatos em triagem
- [ ] Highlight visual ao clicar em um contato (sem abrir chat)
- [ ] Rota protegida + redirect pós-login para `whatsapp_agent`
- [ ] Dados estáticos no controller

### Fora do escopo (não implementar nesta feature)

- Painel de chat / histórico de mensagens
- Campo de resposta e envio de mensagens
- Botões: assumir ticket, encerrar, registrar pedido
- Webhook WhatsApp, API Meta/Z-API
- Laravel Broadcasting / Echo
- Queries reais em `wa_tickets` e `wa_messages`
- Polling ou real-time
- Segunda rota/página de detalhe do ticket

---

## Layout

### Estrutura geral

```
┌──────────────────────────────────────────────────────────────────┐
│  [sidebar]  │  [topbar]  Atendimento                              │
│  52px       │  subtítulo: data de hoje                            │
│  ícones     ├──────────────────────────────────────────────────  │
│             │  [Em andamento 2]  [Triagem 3]  [Fechados 1]       │
│             │  [🔍  Pesquisar nome...                    ]         │
│             ├──────────────────────────────────────────────────  │
│             │  ┌──────────────────────────────────────────────┐  │
│             │  │ (MS)  Maria Silva          14:32      [Novo] │  │
│             │  │       Oi, quero fazer um pedido...           │  │
│             │  ├──────────────────────────────────────────────┤  │
│             │  │ (JP)  João Pereira         ontem             │  │
│             │  │       Qual o horário de entrega?             │  │
│             │  └──────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Sidebar

- Reutilizar `AppSidebar.vue` (mesmo padrão do admin/cozinha)
- Largura: `52px`
- Item ativo nesta tela: `whatsapp` (ícone de chat/mensagem)
- Rota do item: `/whatsapp/inbox`
- Demais itens sem rota: `disabled` (opacidade 40%)

> Se o sidebar do agente for minimalista, pode ter apenas: avatar, item Atendimento (ativo), logout no menu do avatar.

### Topbar (`AppTopbar.vue`)

| Prop | Valor |
|---|---|
| `title` | `"Atendimento"` |
| `subtitle` | data atual `d/m/Y` (prop `date` do controller) |
| `roleBadge` | `"WhatsApp"` |

---

## Abas

Ordem fixa (da esquerda para a direita):

| # | Label UI | `wa_tickets.status` | Aba ativa ao abrir a página |
|---|---|---|---|
| 1 | Em andamento | `in_progress` | **sim** (default) |
| 2 | Triagem | `triage` | não |
| 3 | Fechados | `closed` | não |

### Comportamento

- Apenas uma aba ativa por vez (`activeTab`: `'in_progress' | 'triage' | 'closed'`)
- Aba ativa: texto `#0C447C`, borda inferior `2px solid #378ADD`
- Aba inativa: texto `#7b7f89`, sem borda
- Badge de contagem ao lado do label: número de tickets naquele status
  - Fundo `#E6F1FB`, texto `#0C447C`, `font-size: 11px`, `border-radius: 10px`, `padding: 1px 6px`
- Ao trocar de aba: limpar seleção de contato (`selectedId = null`) e manter o texto do filtro (filtro continua aplicado na nova aba)

---

## Filtro de nomes

- Posicionado abaixo das abas, largura total da área de conteúdo
- Placeholder: `"Pesquisar nome..."`
- Ícone de lupa à esquerda (SVG inline ou caractere)
- Filtra **client-side** apenas os tickets da aba ativa
- Campos considerados: `customer_name` e `phone_number` (case-insensitive, trim)
- Se `customer_name` for null, o ticket ainda aparece se o telefone bater com o termo
- Sem debounce obrigatório no MVP (filtro instantâneo via `computed`)

---

## Lista de contatos

### Ordenação

| Status | Ordenar por |
|---|---|
| `triage` | `created_at ASC` (mais antigo primeiro — FIFO) |
| `in_progress` | `updated_at DESC` |
| `closed` | `updated_at DESC` (apenas fechados do dia no mock; query real filtra `DATE(updated_at) = hoje`) |

### Linha de contato (`WaContactRow.vue`)

| Elemento | Fonte | Regra de exibição |
|---|---|---|
| Avatar | `customer_name` ou `phone_number` | Iniciais (máx. 2 letras, uppercase). Se sem nome: primeiros dígitos ou `?` |
| Nome | `customer_name` | Fallback: `phone_number` formatado `(11) 99999-9999` |
| Preview | `last_message` | Uma linha, `text-overflow: ellipsis`, cor `#7b7f89`, máx. ~60 caracteres |
| Horário | `updated_at` | Hoje → `HH:mm`; ontem → `"ontem"`; anterior → `DD/MM` |
| Badge `Novo` | `status === 'triage'` | Visível apenas na aba Triagem; fundo `#378ADD`, texto branco |
| Estado selecionado | clique | Fundo `#E6F1FB`, borda esquerda `3px solid #378ADD` |

### Clique no contato

- Define `selectedId` localmente (highlight)
- **Não** navega para outra rota
- **Não** dispara request ao backend

### Estado vazio

Quando a lista filtrada estiver vazia:

| Situação | Mensagem |
|---|---|
| Aba sem tickets | `"Nenhum chamado nesta aba"` |
| Filtro sem resultados | `"Nenhum contato encontrado para \"{termo}\""` |

Centralizar texto, cor `#7b7f89`, `padding: 48px 16px`.

---

## Entidades envolvidas

### `wa_tickets` (leitura — mock no MVP)

| Coluna | Tipo | Uso na tela |
|---|---|---|
| `id` | uuid | identificador do ticket / seleção |
| `phone_number` | string E.164 | fallback de nome, filtro |
| `customer_name` | string nullable | nome principal, filtro, avatar |
| `status` | enum | define em qual aba aparece |
| `agent_id` | string nullable | não exibir no MVP |
| `created_at` | timestamp | ordenação triagem |
| `updated_at` | timestamp | horário na lista |

### `last_message` (campo derivado — mock apenas)

No MVP o controller inclui `last_message` como string no array de cada ticket. Na fase 2:

```sql
SELECT body FROM wa_messages
WHERE wa_ticket_id = ?
ORDER BY COALESCE(sent_at, created_at) DESC
LIMIT 1
```

---

## Dados estáticos (MVP)

```php
// app/Http/Controllers/WhatsApp/InboxController.php

private function getTickets(): array
{
    return [
        'in_progress' => [
            [
                'id'            => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                'customer_name' => 'Maria Silva',
                'phone_number'  => '+5511999887766',
                'last_message'  => 'Pode confirmar se o pedido já saiu?',
                'updated_at'    => now()->subMinutes(12)->toIso8601String(),
            ],
            [
                'id'            => 'b2c3d4e5-f6a7-8901-bcde-f12345678901',
                'customer_name' => 'Carlos Mendes',
                'phone_number'  => '+5511988776655',
                'last_message'  => 'Quero alterar o endereço de entrega',
                'updated_at'    => now()->subHour()->toIso8601String(),
            ],
        ],
        'triage' => [
            [
                'id'            => 'c3d4e5f6-a7b8-9012-cdef-123456789012',
                'customer_name' => 'Ana Costa',
                'phone_number'  => '+5511977665544',
                'last_message'  => 'Oi, quero fazer um pedido de delivery',
                'updated_at'    => now()->subMinutes(3)->toIso8601String(),
            ],
            [
                'id'            => 'd4e5f6a7-b8c9-0123-defa-234567890123',
                'customer_name' => null,
                'phone_number'  => '+5511966554433',
                'last_message'  => 'Vocês estão abertos agora?',
                'updated_at'    => now()->subMinutes(25)->toIso8601String(),
            ],
            [
                'id'            => 'e5f6a7b8-c9d0-1234-efab-345678901234',
                'customer_name' => 'Pedro Lima',
                'phone_number'  => '+5511955443322',
                'last_message'  => 'Tem promoção hoje?',
                'updated_at'    => now()->subHours(2)->toIso8601String(),
            ],
        ],
        'closed' => [
            [
                'id'            => 'f6a7b8c9-d0e1-2345-fabc-456789012345',
                'customer_name' => 'Juliana Rocha',
                'phone_number'  => '+5511944332211',
                'last_message'  => 'Obrigada! Pedido recebido.',
                'updated_at'    => now()->subHours(4)->toIso8601String(),
            ],
        ],
    ];
}
```

Props enviadas ao Inertia:

```php
return Inertia::render('WhatsApp/Inbox', [
    'tickets' => $this->getTickets(),
    'date'    => now()->format('d/m/Y'),
]);
```

> Quando as queries reais forem implementadas, manter a mesma estrutura do array `tickets` agrupado por status para não quebrar o Vue.

---

## Rotas

```php
// routes/web.php — dentro do grupo firebase.auth + force.password.reset

Route::middleware(['role:whatsapp_agent'])->prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
});
```

| Método | URI | Controller@method | Middleware |
|---|---|---|---|
| GET | `/whatsapp/inbox` | `WhatsApp\InboxController@index` | `firebase.auth`, `force.password.reset`, `role:whatsapp_agent` |

### Redirect pós-login

Em `AuthController::redirectByRole()`:

```php
'whatsapp_agent' => redirect()->route('whatsapp.inbox'),
```

---

## Implementação — backend

### Arquivo: `app/Http/Controllers/WhatsApp/InboxController.php`

```php
<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WhatsApp/Inbox', [
            'tickets' => $this->getTickets(),
            'date'    => now()->format('d/m/Y'),
        ]);
    }

    private function getTickets(): array
    {
        // copiar array de "Dados estáticos (MVP)" acima
    }
}
```

---

## Implementação — frontend

### Arquivos a criar

| Arquivo | Responsabilidade |
|---|---|
| `resources/js/Pages/WhatsApp/Inbox.vue` | Shell, abas, busca, lista, estado vazio |
| `resources/js/Pages/WhatsApp/styles/Inbox.css` | Estilos da página |
| `resources/js/Components/WaContactRow.vue` | Uma linha da lista de contatos |
| `resources/js/Components/styles/WaContactRow.css` | Estilos da linha |

### `Pages/WhatsApp/Inbox.vue` — estrutura

```vue
<script setup>
import { computed, ref } from 'vue';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import WaContactRow from '../../Components/WaContactRow.vue';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
        // { in_progress: [], triage: [], closed: [] }
    },
    date: {
        type: String,
        required: true,
    },
});

const activeTab = ref('in_progress');
const search = ref('');
const selectedId = ref(null);

const tabDefs = [
    { key: 'in_progress', label: 'Em andamento' },
    { key: 'triage',      label: 'Triagem' },
    { key: 'closed',      label: 'Fechados' },
];

const currentList = computed(() => props.tickets[activeTab.value] ?? []);

const filteredList = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return currentList.value;

    return currentList.value.filter((ticket) => {
        const name  = (ticket.customer_name ?? '').toLowerCase();
        const phone = (ticket.phone_number ?? '').toLowerCase();
        return name.includes(term) || phone.includes(term);
    });
});

function tabCount(key) {
    return (props.tickets[key] ?? []).length;
}

function onTabChange(key) {
    activeTab.value = key;
    selectedId.value = null;
}

function onSelect(ticketId) {
    selectedId.value = ticketId;
}

function formatTime(isoString) {
    // implementar: HH:mm | ontem | DD/MM conforme regras da lista
}
</script>

<template>
  <div class="shell">
    <AppSidebar active="whatsapp" />
    <div class="main">
      <AppTopbar title="Atendimento" :subtitle="date" role-badge="WhatsApp" />

      <div class="content">
        <nav class="tabs">
          <button
            v-for="tab in tabDefs"
            :key="tab.key"
            type="button"
            class="tab"
            :class="{ 'tab--active': activeTab === tab.key }"
            @click="onTabChange(tab.key)"
          >
            {{ tab.label }}
            <span class="tab-badge">{{ tabCount(tab.key) }}</span>
          </button>
        </nav>

        <div class="search-wrap">
          <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10.5 3a7.5 7.5 0 1 1 4.47 13.58l4.19 4.19-1.41 1.41-4.19-4.19A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
          </svg>
          <input
            v-model="search"
            type="search"
            class="search-input"
            placeholder="Pesquisar nome..."
            aria-label="Pesquisar nome"
          />
        </div>

        <div v-if="filteredList.length === 0" class="empty-state">
          <p v-if="search.trim()">
            Nenhum contato encontrado para "{{ search.trim() }}"
          </p>
          <p v-else>Nenhum chamado nesta aba</p>
        </div>

        <ul v-else class="contact-list">
          <WaContactRow
            v-for="ticket in filteredList"
            :key="ticket.id"
            :ticket="ticket"
            :selected="selectedId === ticket.id"
            :show-novo-badge="activeTab === 'triage'"
            :time-label="formatTime(ticket.updated_at)"
            @select="onSelect"
          />
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped src="./styles/Inbox.css"></style>
```

### `Components/WaContactRow.vue` — props

```vue
<script setup>
defineProps({
    ticket: {
        type: Object,
        required: true,
        // { id, customer_name, phone_number, last_message, updated_at }
    },
    selected: { type: Boolean, default: false },
    showNovoBadge: { type: Boolean, default: false },
    timeLabel: { type: String, required: true },
});

const emit = defineEmits(['select']);

function displayName(ticket) {
    if (ticket.customer_name) return ticket.customer_name;
    // formatar phone_number para exibição
    return ticket.phone_number;
}

function initials(ticket) {
    const name = ticket.customer_name ?? '';
    if (!name.trim()) return '?';
    const parts = name.trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : parts[0].slice(0, 2).toUpperCase();
}
</script>

<template>
  <li>
    <button
      type="button"
      class="contact-row"
      :class="{ 'contact-row--selected': selected }"
      @click="emit('select', ticket.id)"
    >
      <span class="avatar">{{ initials(ticket) }}</span>
      <span class="body">
        <span class="head">
          <span class="name">{{ displayName(ticket) }}</span>
          <span class="meta">
            <span v-if="showNovoBadge" class="badge-novo">Novo</span>
            <span class="time">{{ timeLabel }}</span>
          </span>
        </span>
        <span class="preview">{{ ticket.last_message }}</span>
      </span>
    </button>
  </li>
</template>
```

### Ajuste em `AppSidebar.vue`

Adicionar item de navegação (exemplo):

```js
{ key: 'whatsapp', label: 'Atendimento', icon: 'whatsapp', route: '/whatsapp/inbox' },
```

Ícone SVG sugerido: balão de chat. Item `active` quando `props.active === 'whatsapp'`.

> O sidebar do agente pode exibir só este item + avatar; itens admin (cadastros, etc.) não devem aparecer para `whatsapp_agent` — tratar na fase 2 com sidebar por role ou lista condicional.

---

## Design tokens

| Token | Valor | Uso |
|---|---|---|
| Shell / fundo | `#f6f7f9` | background da página |
| Card / lista | `#ffffff` | fundo da área de contatos |
| Borda lista | `#eceef0` | separadores entre linhas |
| Azul primário WhatsApp | `#378ADD` | aba ativa, badge Novo, borda seleção |
| Azul claro | `#E6F1FB` | fundo avatar, item selecionado |
| Azul escuro texto | `#0C447C` | nome, aba ativa, badge count |
| Texto secundário | `#7b7f89` | preview, horário, empty state |
| Texto principal | `#17181e` | nome do contato |
| Sidebar width | `52px` | igual admin |
| Topbar height | `52px` | igual admin |
| Border-radius card | `14px` | container da lista |
| Fonte mono (labels) | `JetBrains Mono` ou `DM Mono` | opcional em badges |

### CSS resumido — `Inbox.css`

```css
.shell { min-height: 100vh; background: #f6f7f9; display: flex; }
.main  { flex: 1; min-width: 0; }
.content { padding: 16px; display: grid; gap: 12px; }

.tabs { display: flex; gap: 4px; border-bottom: 1px solid #eceef0; }
.tab  { padding: 10px 14px; border: 0; background: transparent; color: #7b7f89; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.tab--active { color: #0C447C; border-bottom: 2px solid #378ADD; margin-bottom: -1px; }
.tab-badge { background: #E6F1FB; color: #0C447C; font-size: 11px; border-radius: 10px; padding: 1px 6px; }

.search-wrap { position: relative; }
.search-input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid #eceef0; border-radius: 10px; font-size: 14px; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; fill: #7b7f89; }

.contact-list { list-style: none; margin: 0; padding: 0; background: #fff; border: 1px solid #eceef0; border-radius: 14px; overflow: hidden; }
.empty-state { text-align: center; color: #7b7f89; padding: 48px 16px; }
```

### CSS resumido — `WaContactRow.css`

```css
.contact-row { width: 100%; display: flex; gap: 12px; padding: 12px 14px; border: 0; border-bottom: 1px solid #eef0f2; background: #fff; text-align: left; cursor: pointer; }
.contact-row--selected { background: #E6F1FB; border-left: 3px solid #378ADD; padding-left: 11px; }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #E6F1FB; color: #0C447C; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; flex-shrink: 0; }
.name { font-weight: 600; color: #17181e; font-size: 14px; }
.preview { display: block; color: #7b7f89; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.badge-novo { background: #378ADD; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-right: 6px; }
.time { font-size: 12px; color: #7b7f89; }
.head { display: flex; justify-content: space-between; align-items: center; }
.body { flex: 1; min-width: 0; }
```

---

## Regras de negócio (referência — implementação futura)

Documentadas para não contradizer o MVP:

- Ticket em `triage`: aguardando primeiro atendimento; badge `Novo`
- Ticket em `in_progress`: agente assumiu (`agent_id` preenchido)
- Ticket em `closed`: atendimento encerrado; inbox mostra os do dia
- Um ticket `closed` não aceita novas mensagens (validação no serviço — fase 2)

---

## Status de implementação

- [ ] `docs/features/atendimento.md` revisado
- [ ] Rota `GET /whatsapp/inbox` com middleware `role:whatsapp_agent`
- [ ] `WhatsApp\InboxController@index` com dados estáticos
- [ ] `AuthController::redirectByRole` inclui `whatsapp_agent`
- [ ] `Pages/WhatsApp/Inbox.vue` com abas + busca + lista
- [ ] `Components/WaContactRow.vue`
- [ ] CSS `Inbox.css` e `WaContactRow.css`
- [ ] `AppSidebar` com item Atendimento → `/whatsapp/inbox`
- [ ] Teste manual: login como `whatsapp_agent`, troca de abas, filtro, empty state, seleção visual

---

## Próxima feature sugerida

`whatsapp-chat` — ao clicar no contato, abrir painel direito (ou rota `/whatsapp/inbox/{ticket}`) com histórico `wa_messages`, campo de resposta e ações assumir/encerrar/registrar pedido.
