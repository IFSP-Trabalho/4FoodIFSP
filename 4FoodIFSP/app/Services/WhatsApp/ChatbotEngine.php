<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotEngine
{
    /** Tentativas inválidas seguidas antes do handoff automático. */
    private const MAX_INVALID = 3;

    public function __construct(private OutboundWhatsAppMessageService $sender) {}

    /**
     * Processa UMA mensagem inbound. No-op se não houver fluxo ativo aplicável
     * (preserva 100% o comportamento atual sem bot).
     *
     * Invariante: roda FORA da transação do handleInbound — o ticket e a
     * mensagem inbound já existem quando o motor é chamado.
     *
     * @param  array  $data  payload do webhook: connection_id, phone_number, body, ...
     */
    public function handle(array $data): void
    {
        $phone        = $data['phone_number'];
        $connectionId = $data['connection_id'] ?? null;
        $body         = isset($data['body']) ? trim($data['body']) : '';

        // 1. Fluxo ativo: conexão específica tem prioridade sobre o global (null).
        $flow = DB::table('chatbot_flows')
            ->where('active', true)
            ->where(function ($q) use ($connectionId) {
                $q->whereNull('wa_connection_id');
                if ($connectionId) {
                    $q->orWhere('wa_connection_id', $connectionId);
                }
            })
            ->orderByRaw('wa_connection_id IS NULL') // 0 (específico) antes de 1 (global)
            ->first();

        if (! $flow) {
            return; // sem bot: comportamento atual preservado
        }

        // O motor anexa suas respostas ao ticket aberto já criado pelo inbound.
        $ticket = $this->resolveOpenTicket($phone);
        if (! $ticket) {
            return;
        }
        $ticketId = $ticket->id;

        // 2. Sessão ativa para este número?
        $activeSession = DB::table('chatbot_sessions')
            ->where('phone_number', $phone)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->first();

        if ($activeSession) {
            $this->continueSession($flow, $activeSession, $body, $ticketId, $connectionId, $phone);

            return;
        }

        // Sem sessão ativa: decidir entre reiniciar ou silenciar.
        $latest = DB::table('chatbot_sessions')
            ->where('phone_number', $phone)
            ->orderByDesc('updated_at')
            ->first();

        // Fluxo já encerrado (completed) ou transferido (handoff): o bot fica em
        // silêncio — NÃO reinicia o menu. É isso que "força a parada" quando o
        // cliente chega ao fim de uma opção. Para reengajar, encerre/reabra o
        // atendimento ou limpe a sessão do contato.
        if ($latest && in_array($latest->status, ['handoff', 'completed'], true)) {
            return;
        }

        // Invariante "um condutor só": não iniciar o bot por cima de uma conversa
        // que um humano já assumiu (ticket em atendimento ou com atendente
        // vinculado). Só auto-inicia em ticket fresco de triagem, sem dono humano.
        if ($ticket->status !== 'triage' || $ticket->agent_id !== null) {
            return;
        }

        // Primeiro contato (sem sessão) → inicia o fluxo, respeitando o gatilho.
        if ($flow->trigger_keyword && strcasecmp($body, $flow->trigger_keyword) !== 0) {
            return;
        }

        $this->startSession($flow, $phone, $ticketId, $connectionId);
    }

    /**
     * Cria a sessão no nó start e dispara a 1ª interação.
     */
    private function startSession(object $flow, string $phone, string $ticketId, ?string $connectionId): void
    {
        $startNode = DB::table('chatbot_nodes')
            ->where('chatbot_flow_id', $flow->id)
            ->where('type', 'start')
            ->first();

        if (! $startNode) {
            return;
        }

        $sessionId = (string) Str::uuid();
        $now = now();

        DB::table('chatbot_sessions')->insert([
            'id'                  => $sessionId,
            'chatbot_flow_id'     => $flow->id,
            'phone_number'        => $phone,
            'current_node_id'     => $startNode->id,
            'context'             => json_encode(['invalid_count' => 0]),
            'status'              => 'active',
            'last_interaction_at' => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $session = DB::table('chatbot_sessions')->where('id', $sessionId)->first();

        $this->runFrom($flow, $session, $startNode, $ticketId, $connectionId, $phone);
    }

    /**
     * Sessão ativa esperando input: casar $body com uma aresta do nó atual.
     */
    private function continueSession(object $flow, object $session, string $body, string $ticketId, ?string $connectionId, string $phone): void
    {
        $currentNode = DB::table('chatbot_nodes')->where('id', $session->current_node_id)->first();
        if (! $currentNode) {
            // Estado inconsistente (nó removido): encerra para reiniciar depois.
            $this->updateSession($session->id, null, 'completed');

            return;
        }

        $edge = DB::table('chatbot_edges')
            ->where('chatbot_flow_id', $flow->id)
            ->where('from_node_id', $currentNode->id)
            ->where('match_value', $body)
            ->first();

        if ($edge) {
            $next = DB::table('chatbot_nodes')->where('id', $edge->to_node_id)->first();
            if (! $next) {
                $this->updateSession($session->id, $currentNode->id, 'completed');

                return;
            }

            $this->setContext($session->id, ['invalid_count' => 0]);
            $this->runFrom($flow, $session, $next, $ticketId, $connectionId, $phone);

            return;
        }

        // Não casou nenhuma opção.
        $context = $this->context($session);
        $invalid = ($context['invalid_count'] ?? 0) + 1;
        $context['invalid_count'] = $invalid;

        if ($invalid >= self::MAX_INVALID) {
            $this->sender->send($ticketId, $connectionId, $phone, 'Não consegui te entender. Vou te transferir para um atendente. 👤');
            $now = now();
            DB::table('chatbot_sessions')->where('id', $session->id)->update([
                'status'              => 'handoff',
                'context'             => json_encode($context),
                'last_interaction_at' => $now,
                'updated_at'          => $now,
            ]);

            return;
        }

        $payload = $this->payload($currentNode);
        $reprompt = "Opção inválida. Tente novamente.\n\n" . ($payload['text'] ?? '');
        $this->sender->send($ticketId, $connectionId, $phone, $reprompt);
        $this->setContext($session->id, $context);
    }

    /**
     * Entra num nó e avança por arestas automáticas até parar:
     * envia o texto, executa a ação (handoff/end) ou aguarda input do cliente.
     */
    private function runFrom(object $flow, object $session, object $node, string $ticketId, ?string $connectionId, string $phone): void
    {
        while (true) {
            $payload = $this->payload($node);

            if (! empty($payload['text'])) {
                $this->sender->send($ticketId, $connectionId, $phone, $payload['text']);
            }

            $action = $payload['action'] ?? null;
            if ($action === 'handoff') {
                $this->updateSession($session->id, $node->id, 'handoff');

                return;
            }
            if ($action === 'end') {
                $this->updateSession($session->id, $node->id, 'completed');

                return;
            }

            // Aresta automática (match_value null): avança sem esperar input.
            $autoEdge = DB::table('chatbot_edges')
                ->where('chatbot_flow_id', $flow->id)
                ->where('from_node_id', $node->id)
                ->whereNull('match_value')
                ->first();

            if ($autoEdge) {
                $nextNode = DB::table('chatbot_nodes')->where('id', $autoEdge->to_node_id)->first();
                if (! $nextNode) {
                    $this->updateSession($session->id, $node->id, 'completed');

                    return;
                }
                $node = $nextNode;

                continue;
            }

            // Tem opções de saída? Então aguarda a resposta do cliente.
            $hasOptions = DB::table('chatbot_edges')
                ->where('chatbot_flow_id', $flow->id)
                ->where('from_node_id', $node->id)
                ->exists();

            $this->updateSession($session->id, $node->id, $hasOptions ? 'active' : 'completed');

            return;
        }
    }

    /**
     * Localiza o ticket aberto deste número (mesma lógica do handleInbound:
     * casa pelo JID completo ou pelo número local). Retorna o registro completo
     * para que o chamador possa checar status/agent_id (dono humano).
     */
    private function resolveOpenTicket(string $phone): ?object
    {
        $localPart = explode('@', $phone)[0];

        return DB::table('wa_tickets')
            ->where(function ($q) use ($phone, $localPart) {
                $q->where('phone_number', $phone)
                  ->orWhere('phone_number', $localPart);
            })
            ->whereIn('status', ['triage', 'in_progress'])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function updateSession(string $sessionId, ?string $nodeId, string $status): void
    {
        $now = now();
        DB::table('chatbot_sessions')->where('id', $sessionId)->update([
            'current_node_id'     => $nodeId,
            'status'              => $status,
            'last_interaction_at' => $now,
            'updated_at'          => $now,
        ]);
    }

    private function setContext(string $sessionId, array $context): void
    {
        $now = now();
        DB::table('chatbot_sessions')->where('id', $sessionId)->update([
            'context'             => json_encode($context),
            'last_interaction_at' => $now,
            'updated_at'          => $now,
        ]);
    }

    private function context(object $session): array
    {
        return json_decode($session->context ?? '{}', true) ?: [];
    }

    private function payload(object $node): array
    {
        return json_decode($node->payload ?? '{}', true) ?: [];
    }
}
