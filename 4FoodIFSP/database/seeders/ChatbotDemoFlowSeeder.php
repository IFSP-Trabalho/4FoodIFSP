<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotDemoFlowSeeder extends Seeder
{
    /**
     * Semeia o fluxo de teste hardcoded da Fase 1 (motorFase1.md §3).
     *
     *   [start] --auto--> [N1: menu]
     *                        |- "1" -> [N2: cardápio]   (action: end)
     *                        |- "2" -> [N3: pedido]     (action: handoff)
     *                        |- "3" -> [N4: reserva]    (action: handoff)
     *                        `- "0" -> [N5: atendente]  (action: handoff)
     *
     * Idempotente: remove o fluxo demo anterior (cascade em nodes/edges/sessions)
     * e o recria. Semeado com active=true e wa_connection_id=null (vale para
     * qualquer conexão na Fase 1); trigger_keyword=null dispara na 1ª mensagem.
     */
    public function run(): void
    {
        $flowName = 'Atendimento principal (demo)';

        DB::table('chatbot_flows')->where('name', $flowName)->delete();

        $now = now();

        $flowId = (string) Str::uuid();
        DB::table('chatbot_flows')->insert([
            'id'               => $flowId,
            'wa_connection_id' => null,
            'name'             => $flowName,
            'active'           => true,
            'trigger_keyword'  => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $menuText = "Olá! 👋 Bem-vindo à 4Food. Como posso ajudar?\n\n"
            . "1 - Ver cardápio\n"
            . "2 - Acompanhar meu pedido\n"
            . "3 - Reservar uma mesa\n"
            . "0 - Falar com um atendente";

        $start = (string) Str::uuid();
        $n1    = (string) Str::uuid();
        $n2    = (string) Str::uuid();
        $n3    = (string) Str::uuid();
        $n4    = (string) Str::uuid();
        $n5    = (string) Str::uuid();

        $node = fn(string $id, string $type, ?array $payload, int $x, int $y) => [
            'id'              => $id,
            'chatbot_flow_id' => $flowId,
            'type'            => $type,
            'payload'         => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'position_x'      => $x,
            'position_y'      => $y,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        DB::table('chatbot_nodes')->insert([
            $node($start, 'start',   null, 0, 0),
            $node($n1, 'message', ['text' => $menuText], 0, 120),
            $node($n2, 'message', ['text' => 'Nosso cardápio: https://4food.example/cardapio 🍔 Algo mais? Digite 0 para um atendente.', 'action' => 'end'], -300, 320),
            $node($n3, 'message', ['text' => 'Vou te transferir para acompanhar seu pedido 👇', 'action' => 'handoff'], -100, 320),
            $node($n4, 'message', ['text' => 'Boa! Vou te passar para um atendente concluir sua reserva.', 'action' => 'handoff'], 100, 320),
            $node($n5, 'message', ['text' => 'Certo! Já estou chamando um atendente. 👤', 'action' => 'handoff'], 300, 320),
        ]);

        $edge = fn(string $from, string $to, ?string $match, ?string $label) => [
            'id'              => (string) Str::uuid(),
            'chatbot_flow_id' => $flowId,
            'from_node_id'    => $from,
            'to_node_id'      => $to,
            'match_value'     => $match,
            'label'           => $label,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        DB::table('chatbot_edges')->insert([
            $edge($start, $n1, null, null),            // auto: start -> menu
            $edge($n1, $n2, '1', 'Ver cardápio'),
            $edge($n1, $n3, '2', 'Acompanhar pedido'),
            $edge($n1, $n4, '3', 'Reservar mesa'),
            $edge($n1, $n5, '0', 'Falar com atendente'),
        ]);
    }
}
