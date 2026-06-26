<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Chatbot/Index', [
            'bots' => $this->getBots(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        DB::table('chatbot_flows')->insert([
            'id'               => (string) Str::uuid(),
            'name'             => $validated['name'],
            'active'           => false,
            'wa_connection_id' => null,
            'trigger_keyword'  => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return redirect()->route('admin.chatbot.index');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'active' => ['boolean'],
        ]);

        DB::table('chatbot_flows')->where('id', $id)->update([
            'name'       => $validated['name'],
            'active'     => $request->boolean('active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.chatbot.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        DB::table('chatbot_flows')->where('id', $id)->delete();

        return redirect()->route('admin.chatbot.index');
    }

    public function flow(string $id): Response
    {
        $flow = DB::table('chatbot_flows')->where('id', $id)->first(['id', 'name', 'active']);
        abort_unless($flow, 404);

        $nodes = DB::table('chatbot_nodes')
            ->where('chatbot_flow_id', $id)
            ->orderBy('created_at')
            ->get(['id', 'type', 'payload', 'position_x', 'position_y'])
            ->map(fn (object $node): array => [
                'id'         => (string) $node->id,
                'type'       => (string) $node->type,
                'payload'    => $node->payload ? json_decode($node->payload, true) : null,
                'position_x' => (int) $node->position_x,
                'position_y' => (int) $node->position_y,
            ])
            ->values()
            ->all();

        $edges = DB::table('chatbot_edges')
            ->where('chatbot_flow_id', $id)
            ->get(['id', 'from_node_id', 'to_node_id', 'match_value', 'label'])
            ->map(fn (object $edge): array => [
                'id'           => (string) $edge->id,
                'from_node_id' => (string) $edge->from_node_id,
                'to_node_id'   => (string) $edge->to_node_id,
                'match_value'  => $edge->match_value,
                'label'        => $edge->label,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Chatbot/Flow', [
            'flow' => [
                'id'     => (string) $flow->id,
                'name'   => (string) $flow->name,
                'active' => (bool) $flow->active,
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    public function saveFlow(Request $request, string $id): RedirectResponse
    {
        $flow = DB::table('chatbot_flows')->where('id', $id)->first();
        abort_unless($flow, 404);

        $validated = $request->validate([
            'nodes'              => ['present', 'array'],
            'nodes.*.id'         => ['required', 'string'],
            'nodes.*.type'       => ['required', 'in:start,message,action'],
            'nodes.*.payload'    => ['nullable', 'array'],
            'nodes.*.position_x' => ['nullable', 'integer'],
            'nodes.*.position_y' => ['nullable', 'integer'],
            'edges'                => ['present', 'array'],
            'edges.*.from_node_id' => ['required', 'string'],
            'edges.*.to_node_id'   => ['required', 'string'],
            'edges.*.match_value'  => ['nullable', 'string'],
            'edges.*.label'        => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($id, $validated) {
            // 1. Limpa o fluxo atual (as arestas caem por cascade ao remover os nós).
            DB::table('chatbot_nodes')->where('chatbot_flow_id', $id)->delete();

            $now   = now();
            $idMap = []; // id temporário do front -> novo UUID real

            // 2 + 3. Reinsere os nós com novos UUIDs, guardando o mapeamento.
            foreach ($validated['nodes'] as $node) {
                $newId = (string) Str::uuid();
                $idMap[$node['id']] = $newId;

                DB::table('chatbot_nodes')->insert([
                    'id'              => $newId,
                    'chatbot_flow_id' => $id,
                    'type'            => $node['type'],
                    'payload'         => isset($node['payload'])
                        ? json_encode($node['payload'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'position_x'      => $node['position_x'] ?? 0,
                    'position_y'      => $node['position_y'] ?? 0,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }

            // 4. Reinsere as arestas com os IDs resolvidos pelo mapa.
            foreach ($validated['edges'] as $edge) {
                $from = $idMap[$edge['from_node_id']] ?? null;
                $to   = $idMap[$edge['to_node_id']] ?? null;

                if (! $from || ! $to) {
                    continue; // aresta apontando para nó inexistente é descartada
                }

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
    }

    private function getBots(): array
    {
        return DB::table('chatbot_flows')
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (object $flow): array => [
                'id'     => (string) $flow->id,
                'name'   => (string) $flow->name,
                'active' => (bool) $flow->active,
            ])
            ->values()
            ->all();
    }
}
