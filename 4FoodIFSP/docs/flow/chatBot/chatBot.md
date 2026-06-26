# Spec — Módulo Chatbot (4FoodIFSP)

> **Estado:** atualizado em 2026-06-25.
> Seções marcadas com ✅ já foram implementadas. As demais ainda precisam ser feitas.

---

## Stack e padrões do projeto

| Item | Valor |
|---|---|
| Backend | Laravel 11, sem Eloquent para chatbot — usar `DB::table()` |
| Frontend | Vue 3 `<script setup>` + Inertia.js (`@inertiajs/vue3`) |
| Roteamento front | `router.visit()` / `useForm` do Inertia — sem axios direto |
| CSS | Arquivo separado em `styles/`, sem Tailwind, sem framework de UI |
| IDs | UUID no banco (`Str::uuid()`), strings `temp-*` no front antes de salvar |
| Após mutações | Sempre `redirect()` do Laravel — nunca retornar JSON nas rotas web |
| Não mexer | `ChatbotEngine.php`, migrations existentes |

---

## Banco de dados (já existe, não alterar)

```
chatbot_flows   — id (uuid), name, active, wa_connection_id, trigger_keyword
chatbot_nodes   — id (uuid), chatbot_flow_id, type enum('start','message','action'), payload (json), position_x, position_y
chatbot_edges   — id (uuid), chatbot_flow_id, from_node_id, to_node_id, match_value, label
chatbot_sessions — (motor interno, não mexer)
```

Cascade: deletar `chatbot_nodes` deleta as `chatbot_edges` vinculadas automaticamente (FK + cascadeOnDelete).

---

## O que já foi implementado ✅

### Flow.vue — tela de edição visual do fluxo

**Arquivo:** `resources/js/Pages/Admin/Chatbot/Flow.vue`

Canvas visual usando `@vue-flow/core` (já instalado). Layout full-screen sem `AppSidebar`.

**Props recebidas do backend:**
```js
flow:  { id, name, active }            // dados do chatbot_flow
nodes: [{ id, type, payload, position_x, position_y }]   // chatbot_nodes
edges: [{ id, from_node_id, to_node_id, match_value, label }] // chatbot_edges
```

**Comportamento dos nós fixos (sempre presentes, gerados no frontend):**
- **Início** (`type: 'start'`) — círculo verde. Se não vier no array `nodes`, é injetado com `id: 'start-default'`. Não deletável.
- **Configurações** (`type: 'configurations'`) — quadrado cinza/branco com ícone ⚙. Sempre injetado com `id: 'config-fixed'`. Nunca salvo no banco. Não tem handles (não conecta). Não deletável.

**O que o frontend envia no saveFlow (POST `/admin/chatbot/{id}/flow`):**
```json
{
  "nodes": [
    {
      "id": "real-uuid-ou-temp-xxxxxxx",
      "type": "start|message|action",
      "payload": { "name": "...", "text": "...", "action": "handoff|end|null" },
      "position_x": 80, 
      "position_y": 200
    }
  ],
  "edges": [
    {
      "from_node_id": "real-uuid-ou-temp-xxxxxxx",
      "to_node_id":   "real-uuid-ou-temp-xxxxxxx",
      "match_value": "1",
      "label": "1"
    }
  ]
}
```

Observações críticas para o backend:
- O nó `config-fixed` **nunca é enviado** (filtrado no front antes do POST).
- IDs com prefixo `temp-` são novos nós criados no front que ainda não têm UUID real.
- IDs sem prefixo `temp-` são nós que já existiam no banco (mas o backend pode regenerar todos os UUIDs de qualquer forma — mais simples).
- `payload` chega como **objeto** (não string), direto do JS.

**Componentes de nó criados:**
```
resources/js/Components/FlowBuilder/nodes/StartNode.vue
resources/js/Components/FlowBuilder/nodes/MessageNode.vue
resources/js/Components/FlowBuilder/nodes/ActionNode.vue
resources/js/Components/FlowBuilder/nodes/ConfigNode.vue
```

**Dependências npm instaladas:**
```
@vue-flow/core
@vue-flow/background
@vue-flow/controls
@vue-flow/minimap
```

---

## O que ainda precisa ser feito

### 1. Backend — `ChatbotController.php`

**Arquivo:** `app/Http/Controllers/Admin/ChatbotController.php`

O arquivo já existe com apenas o método `index()`. Adicionar os métodos abaixo. Não criar outro controller.

#### `store(Request $request)`
```
Valida: name (string, obrigatório, max:120)
Insere em chatbot_flows: name, active=false, wa_connection_id=null, trigger_keyword=null, id=Str::uuid()
Retorna: redirect()->route('admin.chatbot.index')
```

#### `update(Request $request, string $id)`
```
Valida: name (string, obrigatório, max:120), active (boolean)
Atualiza em chatbot_flows WHERE id=$id: name, active
Retorna: redirect()->route('admin.chatbot.index')
```

#### `destroy(string $id)`
```
DB::table('chatbot_flows')->where('id', $id)->delete()
Retorna: redirect()->route('admin.chatbot.index')
```

#### `flow(string $id)`
```php
$flow  = DB::table('chatbot_flows')->where('id', $id)->first();
$nodes = DB::table('chatbot_nodes')->where('chatbot_flow_id', $id)->get();
$edges = DB::table('chatbot_edges')->where('chatbot_flow_id', $id)->get();

return Inertia::render('Admin/Chatbot/Flow', [
    'flow'  => ['id' => $flow->id, 'name' => $flow->name, 'active' => (bool) $flow->active],
    'nodes' => $nodes->map(fn($n) => [
        'id'         => $n->id,
        'type'       => $n->type,
        'payload'    => json_decode($n->payload ?? '{}', true),
        'position_x' => $n->position_x,
        'position_y' => $n->position_y,
    ])->values()->all(),
    'edges' => $edges->map(fn($e) => [
        'id'          => $e->id,
        'from_node_id'=> $e->from_node_id,
        'to_node_id'  => $e->to_node_id,
        'match_value' => $e->match_value,
        'label'       => $e->label,
    ])->values()->all(),
]);
```

#### `saveFlow(Request $request, string $id)`

Lógica obrigatória — atenção ao mapeamento de IDs temporários:

```php
$nodes = $request->input('nodes', []);
$edges = $request->input('edges', []);

DB::transaction(function () use ($id, $nodes, $edges) {
    // 1. Remove todos os nós existentes (edges fazem cascade)
    DB::table('chatbot_nodes')->where('chatbot_flow_id', $id)->delete();

    // 2. Mapeia old_id → new_uuid para todos os nós
    $idMap = [];
    $now   = now();

    foreach ($nodes as $node) {
        $newId          = (string) Str::uuid();
        $idMap[$node['id']] = $newId;

        DB::table('chatbot_nodes')->insert([
            'id'              => $newId,
            'chatbot_flow_id' => $id,
            'type'            => $node['type'],
            'payload'         => json_encode($node['payload'] ?? []),
            'position_x'      => (int) ($node['position_x'] ?? 0),
            'position_y'      => (int) ($node['position_y'] ?? 0),
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    // 3. Reinsere arestas usando os novos UUIDs
    foreach ($edges as $edge) {
        $from = $idMap[$edge['from_node_id']] ?? null;
        $to   = $idMap[$edge['to_node_id']]   ?? null;

        if (!$from || !$to || $from === $to) continue;

        DB::table('chatbot_edges')->insert([
            'id'              => (string) Str::uuid(),
            'chatbot_flow_id' => $id,
            'from_node_id'    => $from,
            'to_node_id'      => $to,
            'match_value'     => $edge['match_value'] ?? null,
            'label'           => $edge['label'] ?? null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
});

return redirect()->back();
```

---

### 2. Rotas — `routes/web.php`

Adicionar dentro do grupo `Route::middleware('role:admin')->group(...)`, ao lado da rota `GET /chatbot` já existente:

```php
use App\Http\Controllers\Admin\ChatbotController;

Route::post('/chatbot',                [ChatbotController::class, 'store'])->name('chatbot.store');
Route::put('/chatbot/{id}',            [ChatbotController::class, 'update'])->name('chatbot.update');
Route::delete('/chatbot/{id}',         [ChatbotController::class, 'destroy'])->name('chatbot.destroy');
Route::get('/chatbot/{id}/flow',       [ChatbotController::class, 'flow'])->name('chatbot.flow');
Route::post('/chatbot/{id}/flow',      [ChatbotController::class, 'saveFlow'])->name('chatbot.saveFlow');
```

---

### 3. Frontend — `Index.vue`

**Arquivo:** `resources/js/Pages/Admin/Chatbot/Index.vue`

O arquivo existe mas com botões `disabled`. Substituir pelo abaixo.

**Referência visual:** `Components/TableFormModal.vue` para o padrão do modal.

#### Estrutura da página
- Usa `AppSidebar` com `active="chatbot"`
- Topbar com: título "ChatBot", campo de busca, botão "Adicionar"
- Tabela: **Nome** | **Ativo** | **Ações**
- Badge ativo/inativo (classes `.status-badge.on` / `.status-badge.off` já existem no CSS)

#### Ações por linha

| Botão | Comportamento |
|---|---|
| Editar | Abre modal de edição (nome + checkbox ativo) |
| Abrir | `router.visit(route('admin.chatbot.flow', { id: bot.id }))` |
| Excluir | `confirm()` → `router.delete(route('admin.chatbot.destroy', { id: bot.id }))` |

Sem exportar. Sem duplicar.

#### Modal criar/editar (inline, sem componente separado)
- Overlay + div (padrão de `TableFormModal.vue`, não usar `<dialog>` nativo)
- **Criar:** campo Nome (obrigatório). Sem campo Ativo (bot nasce `active=false`).
- **Editar:** campos Nome (obrigatório) + Ativo (checkbox).
- Submete via `useForm` do Inertia.
- Fechar ao clicar fora (`.overlay @click.self`) ou no botão Cancelar.

#### Observações de CSS
- O CSS atual (`styles/Index.css`) já cobre tabela, badges e botões de ação.
- Adicionar ao CSS as classes do modal: overlay, dialog box, campos de form, botões.
- Padrão de cores do projeto: primário `#993c1d` (vermelho-tijolo).

---

## Resumo do que falta fazer

| Arquivo | Ação |
|---|---|
| `app/Http/Controllers/Admin/ChatbotController.php` | Adicionar: `store`, `update`, `destroy`, `flow`, `saveFlow` |
| `routes/web.php` | Adicionar 5 rotas no grupo `role:admin` |
| `resources/js/Pages/Admin/Chatbot/Index.vue` | Substituir: ativar botões, adicionar modal, ações de linha |
| `resources/js/Pages/Admin/Chatbot/styles/Index.css` | Adicionar estilos do modal |

## Resumo do que já existe (não recriar)

| Arquivo | Estado |
|---|---|
| `resources/js/Pages/Admin/Chatbot/Flow.vue` | ✅ Implementado — canvas VueFlow completo |
| `resources/js/Pages/Admin/Chatbot/styles/Flow.css` | ✅ Implementado |
| `resources/js/Components/FlowBuilder/nodes/*.vue` | ✅ Criados (Start, Message, Action, Config) |
| `app/Services/WhatsApp/ChatbotEngine.php` | ✅ Não mexer |
| Migrations `chatbot_*` | ✅ Não mexer |
