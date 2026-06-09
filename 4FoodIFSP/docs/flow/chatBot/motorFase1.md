# Flow: chatbot — Fase 1 (motor)

> **Objetivo desta entrega:** implementar **só o motor** do chatbot — a máquina de estados que, ao receber uma mensagem no WhatsApp, responde automaticamente seguindo um fluxo, avança conforme a resposta do cliente e escala para um humano quando preciso. **Sem construtor visual** (isso é a Fase 2).
>
> **Spec de feature (o "quê"):** [../../features/chatbot-fluxo.md](../../features/chatbot-fluxo.md) — leia primeiro para o desenho geral.
> **Este doc (o "como"):** anchors exatos no código, contrato do `ChatbotEngine`, fluxo de teste hardcoded e ordem de integração.
>
> **Depende de:** [schema.md](../../database/schema.md) (`wa_tickets`, `wa_messages`, `wa_connections`), [atendimento.md](../../features/atendimento.md) (handoff humano)
>
> **Stack:** Laravel 12 · ponte Baileys Node (já roda, envio em `:3001`) · MySQL

---

## ⚠️ Leia antes de codar — 3 invariantes que NÃO podem ser violadas

1. **A chamada HTTP de envio (Baileys) acontece FORA de qualquer `DB::transaction`.** O [`handleInbound`](../../app/Services/WhatsApp/IncomingWhatsAppMessageService.php#L12) abre uma transação; nunca segure uma transação aberta esperando uma requisição HTTP externa. Por isso o motor é chamado **depois** do `handleInbound`, no controller — ver §4.
2. **No máximo um "condutor" da conversa.** Enquanto `chatbot_sessions.status = 'active'`, o **bot** dirige (responde sozinho). Em `handoff`/`completed`, o bot fica **mudo** e o humano dirige. Nunca os dois ao mesmo tempo.
3. **Um tick por mensagem.** Uma mensagem do cliente = uma execução do motor. O grafo é carregado **uma vez** do banco e percorrido em memória até parar num nó que espera input. Transições entre nós **não** são novas requisições HTTP.

---

## 1. O que entregar (escopo da Fase 1)

| # | Item | Arquivo |
|---|---|---|
| 1 | Migrations das 4 tabelas | `database/migrations/..._create_chatbot_*.php` (4) |
| 2 | Seeder com o fluxo de teste hardcoded | `database/seeders/ChatbotDemoFlowSeeder.php` |
| 3 | Serviço de envio outbound (reutilizável) | `app/Services/WhatsApp/OutboundWhatsAppMessageService.php` |
| 4 | Motor (máquina de estados) | `app/Services/WhatsApp/ChatbotEngine.php` |
| 5 | Integração: chamar o motor após o inbound | `app/Http/Controllers/Webhooks/BaileysMessageWebhookController.php` (2 linhas) |

> **Fora do escopo da Fase 1:** canvas/construtor, CRUD de fluxo no admin, IA/texto livre, esconder tickets do bot da inbox humana, timeout de sessão. Tudo isso é Fase 2+.

---

## 2. Migrations (idênticas à spec de feature)

As 4 tabelas estão detalhadas em [chatbot-fluxo.md §Modelo de dados](../../features/chatbot-fluxo.md#modelo-de-dados): `chatbot_flows`, `chatbot_nodes`, `chatbot_edges`, `chatbot_sessions`. Copiar de lá sem alterações.

**Índice obrigatório** em `chatbot_sessions`:

```php
$table->index(['phone_number', 'status']); // buscar a sessão ativa de um número
```

---

## 3. O fluxo de teste (hardcoded via Seeder)

> **Decisão de arquitetura:** o motor SEMPRE lê o grafo do banco (`chatbot_nodes`/`edges`). Na Fase 1 o grafo é populado por um **seeder**; na Fase 2 será populado pelo construtor visual. **O código do motor não muda entre as fases** — só a origem do grafo. Isso garante que a Fase 1 já exercita o runtime real.

### Grafo a semear (`ChatbotDemoFlowSeeder`)

```
[start] ──auto──> [N1: menu]
                     ├─ "1" ─> [N2: cardápio]   (action: end)
                     ├─ "2" ─> [N3: pedido]     (action: handoff)
                     ├─ "3" ─> [N4: reserva]    (action: handoff)
                     └─ "0" ─> [N5: atendente]  (action: handoff)
```

| Nó | type | payload | arestas de saída |
|---|---|---|---|
| start | `start` | — | auto → N1 |
| N1 | `message` | `{ text: "<menu>" }` | "1"→N2, "2"→N3, "3"→N4, "0"→N5 |
| N2 | `message` | `{ text: "Nosso cardápio: <link>. Algo mais? Digite 0 para um atendente." }` + `action:end` | — |
| N3 | `message` | `{ text: "Vou te transferir para acompanhar seu pedido 👇" }` + `action:handoff` | — |
| N4 | `message` | `{ text: "Boa! Vou te passar para um atendente concluir sua reserva." }` + `action:handoff` | — |
| N5 | `message` | `{ text: "Certo! Já estou chamando um atendente." }` + `action:handoff` | — |

Texto do menu (N1):

```
Olá! 👋 Bem-vindo à 4Food. Como posso ajudar?

1 - Ver cardápio
2 - Acompanhar meu pedido
3 - Reservar uma mesa
0 - Falar com um atendente
```

O fluxo é semeado com `active = true` e `wa_connection_id = null` (vale para qualquer conexão na Fase 1). `trigger_keyword = null` → dispara na 1ª mensagem.

---

## 4. Integração — onde o motor entra

**Ponto exato:** [`BaileysMessageWebhookController::handle()`](../../app/Http/Controllers/Webhooks/BaileysMessageWebhookController.php#L11), **depois** de `handleInbound` retornar (logo, fora da transação dele).

```php
public function handle(Request $request, IncomingWhatsAppMessageService $service, ChatbotEngine $engine)
{
    // ... validação de secret e $validated (INALTERADO) ...

    $isNew = $service->handleInbound($validated); // grava ticket + inbound; false se duplicata

    if ($isNew) {                          // ← reentrega do Baileys não reprocessa o bot
        $engine->handle($validated);       // bot decide e responde (fora da transação)
    }

    return response()->json(['ok' => true]);
}
```

> **Dedup obrigatório:** `handleInbound` retorna `bool` (false em reentrega do mesmo `wa_message_id`). O bot **só** roda quando a mensagem é nova — senão a sessão avança e o bot responde em duplicidade a cada retry do webhook.

Por que aqui e não dentro do `handleInbound`:
- O envio Baileys é HTTP → **não pode** rodar dentro da transação do `handleInbound` (invariante #1).
- Mantém o `IncomingWhatsAppMessageService` intocado (menor diff, menor risco).
- Quando o motor roda, o ticket e a mensagem inbound **já existem** — o motor só precisa achar o `wa_ticket_id` para anexar suas respostas outbound.

> ⚠️ `@lid`: o `handleInbound` já ignora `@lid` para contatos. O motor deve usar o `phone_number` **como veio no `$validated`** (mesmo valor usado para achar/gravar o ticket), para casar a mesma sessão.

---

## 5. Contrato do `ChatbotEngine`

```php
namespace App\Services\WhatsApp;

class ChatbotEngine
{
    public function __construct(private OutboundWhatsAppMessageService $sender) {}

    /**
     * Processa UMA mensagem inbound. No-op se não houver fluxo ativo
     * aplicável (preserva 100% o comportamento atual sem bot).
     *
     * @param array $data payload do webhook: connection_id, phone_number, body, ...
     */
    public function handle(array $data): void
    {
        // 1. Achar fluxo ativo para a conexão (ou global, wa_connection_id null).
        //    Nenhum → return (comportamento atual preservado).
        // 2. Achar sessão ativa por [phone_number, status='active'].
        //    Não existe → só criar sessão no 'start' se o ticket for de TRIAGEM
        //    sem agent_id (ninguém humano conduzindo). Ticket in_progress ou com
        //    atendente vinculado → return (invariante #2: bot não atropela humano).
        // 3. Avançar a partir do current_node:
        //    - se chegou agora no start: seguir aresta auto → enviar 1ª message.
        //    - se já estava num nó message à espera: casar $body com edge.match_value.
        //        • casou  → mover para to_node.
        //        • não    → reenviar nó atual com "Opção inválida"; +1 em context.invalid_count;
        //                   se invalid_count >= 3 → handoff.
        // 4. Ao entrar num nó: enviar o texto; se payload.action:
        //    - 'handoff'   → session.status = 'handoff'  (bot silencia; humano assume na inbox)
        //    - 'end'       → session.status = 'completed'
        //    - sem action  → continuar (auto-edge) ou aguardar input.
        // 5. Persistir current_node_id, context, last_interaction_at.
    }
}
```

### Regras de casamento (`$body` → opção)

- `trim()` no corpo; comparar como **string** (`"1"`, `"0"`). Não usar cast int (evita `"1x"` virar 1).
- Casamento exato com `chatbot_edges.match_value` saindo do `current_node`.
- Aresta com `match_value = null` = transição **automática** (não espera input) — usada do `start` para o menu.

### Sessão concluída recebe nova mensagem

Se a única sessão do número está `completed`/`handoff` e chega mensagem nova:
- `handoff` → **no-op** (humano está conduzindo; bot não interfere).
- `completed` → criar **nova** sessão no `start` (reinicia o fluxo).

---

## 6. `OutboundWhatsAppMessageService` (extraído do InboxController)

Reaproveita exatamente o padrão de [`InboxController::send`](../../app/Http/Controllers/WhatsApp/InboxController.php#L107-L127). Assinatura:

```php
/**
 * Envia texto pela ponte Baileys e grava a wa_message outbound no ticket.
 * Retorna false se não houver conexão conectada (motor deve apenas logar e seguir).
 */
public function send(string $waTicketId, string $connectionId, string $to, string $body): bool;
```

Passos (mesmo do InboxController, sem o contexto de auth/HTTP request):
1. Resolver a conexão: usar `connectionId` se `connection_status = 'connected'` e tiver `baileys_session_id`; senão, fallback para a conexão conectada mais recente (mesma lógica das linhas 92‑98).
2. `Http::post("{$baileysUrl}/sessions/{$sessionId}/send", ['to' => $to, 'body' => $body])`.
3. Em sucesso: `insert` em `wa_messages` (`direction='outbound'`, `sent_at=now()`, `wa_ticket_id=$waTicketId`) e `touch` no `updated_at` do ticket.
4. Falha de envio: retornar `false` (na Fase 1, logar; **não** estourar 500 no webhook).

> **Fase 2 (cleanup):** refatorar `InboxController::send` para usar este serviço e eliminar a duplicação. Na Fase 1 **não** mexer no InboxController — só extrair o novo serviço.

`config('services.baileys.url', 'http://127.0.0.1:3001')` é a base (já existe em [config/services.php](../../config/services.php)).

---

## 7. Teste manual ponta a ponta (critério de "Fase 1 pronta")

Pré-requisitos: `composer dev` rodando + ponte Baileys (`services/baileys` → `npm start`) + uma `wa_connections` com `connection_status='connected'` e `baileys_session_id` preenchido (parear via tela de Conexões). Rodar `php artisan db:seed --class=ChatbotDemoFlowSeeder`.

| # | Ação (mandar do celular para o número conectado) | Esperado |
|---|---|---|
| 1 | "oi" (primeira mensagem) | Bot responde o **menu**; cria `chatbot_session` em `active` no nó N1 |
| 2 | "1" | Bot envia texto do cardápio; sessão → `completed` |
| 3 | Mandar "oi" de novo | Bot reenvia o menu (nova sessão) |
| 4 | "9" (inválido) | Bot: "Opção inválida" + menu; `context.invalid_count = 1` |
| 5 | "9", "9" de novo (3 inválidos no total) | Handoff automático; sessão → `handoff`; ticket aparece em **Triagem** na inbox |
| 6 | "0" | Bot: "chamando atendente"; sessão → `handoff` |
| 7 | Conexão **sem** fluxo ativo (desativar o seed) | Bot **não** responde; comportamento atual preservado (ticket normal em triagem) |
| 8 | Com sessão em `handoff`, mandar outra mensagem | Bot **não** responde (humano conduz) |

Verificações no banco: `chatbot_sessions` reflete `current_node_id`/`status` corretos; `wa_messages` tem as respostas `outbound` ligadas ao ticket certo.

---

## 8. Checklist de implementação

- [ ] 4 migrations criadas e migradas (`chatbot_flows`, `_nodes`, `_edges`, `_sessions` + índice `[phone_number, status]`)
- [ ] `ChatbotDemoFlowSeeder` semeia o grafo da §3 (`active=true`, conexão null)
- [ ] `OutboundWhatsAppMessageService::send(...)` enviando via Baileys e gravando outbound
- [ ] `ChatbotEngine::handle(...)` com a máquina de estados da §5
- [ ] `BaileysMessageWebhookController` chama o motor após `handleInbound` (2 linhas)
- [ ] Envio Baileys ocorre **fora** de transação (invariante #1)
- [ ] Roteiro de teste da §7 passa de ponta a ponta
- [ ] Sem regressão: conexão sem fluxo ativo mantém o fluxo humano atual

---

## 9. Entregáveis para a Fase 2 (não fazer agora)

- Construtor visual (Vue Flow) populando `chatbot_nodes`/`edges` no lugar do seeder
- CRUD de fluxo no admin + validação de integridade do grafo (ver [chatbot-fluxo.md](../../features/chatbot-fluxo.md))
- Esconder/realçar tickets conduzidos pelo bot na inbox humana
- Refatorar `InboxController::send` para usar `OutboundWhatsAppMessageService`
- Timeout de sessão ociosa; ação `reservation` integrada com [mesas.md](../../features/mesas.md)
