<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $this->resolvePeriod($request);

        return Inertia::render('Admin/Dashboard', [
            'heroKpis'    => $this->getHeroKpis(),
            'financial'   => $this->getFinancialSummary($period['from'], $period['to']),
            'operational' => $this->getOperationalStatus(),
            'tables'      => $this->getTablesStatus(),
            'whatsapp'    => $this->getWhatsappSummary($period['from'], $period['to']),
            'menu'        => $this->getMenuSummary(),
            'filters'     => $period,
            'date'        => $this->formatDatePtBr(),
        ]);
    }

    public function heroKpis(): JsonResponse
    {
        return response()->json($this->getHeroKpis());
    }

    public function financialSummary(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);

        return response()->json($this->getFinancialSummary($period['from'], $period['to']));
    }

    public function operationalStatus(): JsonResponse
    {
        return response()->json($this->getOperationalStatus());
    }

    public function tablesStatus(): JsonResponse
    {
        return response()->json($this->getTablesStatus());
    }

    public function whatsappSummary(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);

        return response()->json($this->getWhatsappSummary($period['from'], $period['to']));
    }

    public function menuSummary(): JsonResponse
    {
        return response()->json($this->getMenuSummary());
    }

    // ─── Data builders ────────────────────────────────────────────────────────

    private function getHeroKpis(): array
    {
        $today   = now()->toDateString();
        $weekAgo = now()->subDays(7)->toDateString();

        $fat = DB::selectOne("
            SELECT
                COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total,
                COUNT(DISTINCT o.id) AS pedidos_pagos
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.paid = 1 AND DATE(o.created_at) = :today
        ", ['today' => $today]);

        $fatAnt = DB::selectOne("
            SELECT
                COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total,
                COUNT(DISTINCT o.id) AS pedidos_pagos
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.paid = 1 AND DATE(o.created_at) = :day
        ", ['day' => $weekAgo]);

        $pedidos = DB::selectOne("
            SELECT
                COUNT(CASE WHEN status != 'cancelled' THEN 1 END) AS total,
                COUNT(CASE WHEN paid = 1             THEN 1 END) AS pagos,
                COUNT(CASE WHEN paid = 0 AND status != 'cancelled' THEN 1 END) AS abertos
            FROM orders
            WHERE DATE(created_at) = :today
        ", ['today' => $today]);

        $mesas = DB::selectOne("
            SELECT
                COUNT(CASE WHEN ts.id IS NOT NULL THEN 1 END) AS ocupadas,
                COUNT(t.id) AS ativas
            FROM tables t
            LEFT JOIN table_sessions ts ON ts.table_id = t.id AND ts.closed_at IS NULL
            WHERE t.active = 1
        ");

        $wa = DB::selectOne("
            SELECT
                COUNT(CASE WHEN status = 'triage'      THEN 1 END) AS triagem,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS em_atendimento
            FROM wa_tickets
        ");

        $sparkline = DB::select("
            SELECT DATE(o.created_at) AS dia, COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.paid = 1 AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY DATE(o.created_at)
            ORDER BY dia
        ", ['from' => now()->subDays(6)->toDateString(), 'to' => $today]);

        $faturamento    = (float) $fat->total;
        $faturamentoAnt = (float) $fatAnt->total;
        $deltaFat = $faturamentoAnt > 0
            ? round((($faturamento - $faturamentoAnt) / $faturamentoAnt) * 100, 1)
            : null;

        $pedidosPagos    = (int) $fat->pedidos_pagos;
        $pedidosPagosAnt = (int) $fatAnt->pedidos_pagos;
        $ticketMedio     = $pedidosPagos > 0 ? $faturamento / $pedidosPagos : 0;
        $ticketMedioAnt  = $pedidosPagosAnt > 0 ? (float) $fatAnt->total / $pedidosPagosAnt : 0;
        $deltaTicket = $ticketMedioAnt > 0
            ? round((($ticketMedio - $ticketMedioAnt) / $ticketMedioAnt) * 100, 1)
            : null;

        return [
            'faturamento' => [
                'value'     => $this->brl($faturamento),
                'delta'     => $deltaFat,
                'sparkline' => array_map(fn ($r) => (float) $r->total, $sparkline),
            ],
            'ticket_medio' => [
                'value' => $this->brl($ticketMedio),
                'delta' => $deltaTicket,
            ],
            'pedidos' => [
                'total'  => (int) $pedidos->total,
                'pagos'  => (int) $pedidos->pagos,
                'abertos'=> (int) $pedidos->abertos,
            ],
            'mesas' => [
                'ocupadas' => (int) ($mesas->ocupadas ?? 0),
                'ativas'   => (int) ($mesas->ativas ?? 0),
            ],
            'wa_triagem' => [
                'triagem'        => (int) ($wa->triagem ?? 0),
                'em_atendimento' => (int) ($wa->em_atendimento ?? 0),
            ],
            'updated_at' => now()->format('H:i'),
        ];
    }

    private function getFinancialSummary(string $from, string $to): array
    {
        $today = now()->toDateString();

        $porDia = DB::select("
            SELECT DATE(o.created_at) AS dia, COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.status != 'cancelled' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY DATE(o.created_at)
            ORDER BY dia
        ", ['from' => $from, 'to' => $to]);

        $origem = DB::select("
            SELECT o.origin, COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.status != 'cancelled' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY o.origin
        ", ['from' => $from, 'to' => $to]);

        $porHora = DB::select("
            SELECT HOUR(created_at) AS hora, COUNT(*) AS qtd
            FROM orders
            WHERE status != 'cancelled' AND DATE(created_at) = :today
            GROUP BY HOUR(created_at)
            ORDER BY hora
        ", ['today' => $today]);

        $horaBuckets = array_fill(0, 24, 0);
        foreach ($porHora as $r) {
            $horaBuckets[(int) $r->hora] = (int) $r->qtd;
        }
        $maxQtd   = max($horaBuckets);
        $picoHora = $maxQtd > 0 ? array_search($maxQtd, $horaBuckets) : null;

        $diffDays = $from === $to ? 0 : (new \DateTime($from))->diff(new \DateTime($to))->days;
        $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $labelFn  = fn ($dia) => $diffDays <= 7
            ? $weekdays[(int) date('w', strtotime($dia))]
            : date('d/m', strtotime($dia));

        return [
            'por_dia' => array_map(fn ($r) => [
                'label'   => $labelFn($r->dia),
                'value'   => (float) $r->total,
                'display' => $this->brl((float) $r->total),
            ], $porDia),
            'origem' => array_map(fn ($r) => [
                'label'   => $r->origin === 'table' ? 'Mesa' : 'Delivery',
                'value'   => (float) $r->total,
                'display' => $this->brl((float) $r->total),
            ], $origem),
            'por_hora' => array_map(fn ($h, $qtd) => [
                'label'   => sprintf('%02d', $h) . 'h',
                'value'   => $qtd,
                'display' => (string) $qtd,
                'pico'    => $picoHora !== null && $h === $picoHora && $qtd > 0,
            ], array_keys($horaBuckets), $horaBuckets),
            'pico_hora' => $picoHora !== null ? sprintf('%02dh', $picoHora) : null,
        ];
    }

    private function getOperationalStatus(): array
    {
        $today = now()->toDateString();

        $status = DB::selectOne("
            SELECT
                COUNT(CASE WHEN status = 'pending'     THEN 1 END) AS pendentes,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS em_preparo,
                COUNT(CASE WHEN status = 'ready'       THEN 1 END) AS prontos,
                COUNT(CASE WHEN status = 'cancelled'   THEN 1 END) AS cancelados
            FROM orders
            WHERE DATE(created_at) = :today
        ", ['today' => $today]);

        $topPratos = DB::select("
            SELECT d.name, SUM(oi.quantity) AS qtd, SUM(oi.unit_price * oi.quantity) AS total
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN dishes d ON d.id = oi.dish_id
            WHERE o.status != 'cancelled' AND DATE(o.created_at) = :today
            GROUP BY d.id, d.name
            ORDER BY qtd DESC
            LIMIT 5
        ", ['today' => $today]);

        $prontosAguardando = (int) DB::table('orders')
            ->where('status', 'ready')
            ->where('paid', false)
            ->whereDate('created_at', $today)
            ->count();

        $pico = DB::selectOne("
            SELECT HOUR(created_at) AS hora, COUNT(*) AS qtd
            FROM orders
            WHERE status != 'cancelled' AND DATE(created_at) = :today
            GROUP BY HOUR(created_at)
            ORDER BY qtd DESC
            LIMIT 1
        ", ['today' => $today]);

        return [
            'status' => [
                'pendentes'  => (int) $status->pendentes,
                'em_preparo' => (int) $status->em_preparo,
                'prontos'    => (int) $status->prontos,
                'cancelados' => (int) $status->cancelados,
            ],
            'top_pratos' => array_map(fn ($r) => [
                'nome'   => (string) $r->name,
                'qtd'    => (int) $r->qtd,
                'receita'=> $this->brl((float) $r->total),
            ], $topPratos),
            'prontos_aguardando' => $prontosAguardando,
            'cancelados_hoje'    => (int) $status->cancelados,
            'horario_pico'       => $pico ? sprintf('%02dh', (int) $pico->hora) : '—',
        ];
    }

    private function getTablesStatus(): array
    {
        $today = now()->toDateString();

        $mesas = DB::select("
            SELECT
                t.id, t.number, t.label, t.active,
                ts.id         AS session_id,
                ts.started_at
            FROM tables t
            LEFT JOIN table_sessions ts ON ts.table_id = t.id AND ts.closed_at IS NULL
            WHERE t.active = 1
            ORDER BY t.number
        ");

        $kpis = DB::selectOne("
            SELECT
                COUNT(*)                                                            AS sessoes_encerradas,
                AVG(TIMESTAMPDIFF(MINUTE, started_at, closed_at))                  AS tempo_medio
            FROM table_sessions
            WHERE DATE(closed_at) = :today
        ", ['today' => $today]);

        $totalAtivas       = count($mesas);
        $ocupadas          = count(array_filter((array) $mesas, fn ($m) => $m->session_id !== null));
        $sessoesEncerradas = (int) ($kpis->sessoes_encerradas ?? 0);
        $tempoMedio        = $kpis->tempo_medio !== null ? round((float) $kpis->tempo_medio) : null;

        return [
            'mesas' => array_map(fn ($m) => [
                'id'         => $m->id,
                'numero'     => (int) $m->number,
                'label'      => $m->label,
                'ocupada'    => $m->session_id !== null,
                'started_at' => $m->started_at,
            ], $mesas),
            'kpis' => [
                'taxa_ocupacao'      => $totalAtivas > 0 ? round(($ocupadas / $totalAtivas) * 100) : 0,
                'tempo_medio'        => $tempoMedio !== null ? "{$tempoMedio} min" : '—',
                'sessoes_encerradas' => $sessoesEncerradas,
                'giro'               => $totalAtivas > 0 ? round($sessoesEncerradas / $totalAtivas, 1) : 0,
                'ocupadas'           => $ocupadas,
                'ativas'             => $totalAtivas,
            ],
        ];
    }

    private function getWhatsappSummary(string $from, string $to): array
    {
        $today = now()->toDateString();

        $funil = DB::selectOne("
            SELECT
                COUNT(CASE WHEN status = 'triage'                                          THEN 1 END) AS triagem,
                COUNT(CASE WHEN status = 'in_progress'                                     THEN 1 END) AS em_atendimento,
                COUNT(CASE WHEN status = 'closed' AND DATE(updated_at) = :today THEN 1 END) AS encerrados_hoje
            FROM wa_tickets
        ", ['today' => $today]);

        $delivHoje = DB::selectOne("
            SELECT
                COUNT(DISTINCT o.id)                            AS pedidos,
                COALESCE(SUM(oi.unit_price * oi.quantity), 0)  AS receita
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.origin = 'delivery' AND o.status != 'cancelled' AND DATE(o.created_at) = :today
        ", ['today' => $today]);

        $delivHistorico = DB::select("
            SELECT DATE(o.created_at) AS dia, COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.origin = 'delivery' AND o.status != 'cancelled' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY DATE(o.created_at)
            ORDER BY dia
        ", ['from' => $from, 'to' => $to]);

        $delivPedidos = (int) $delivHoje->pedidos;
        $delivReceita = (float) $delivHoje->receita;

        return [
            'funil' => [
                'triagem'         => (int) $funil->triagem,
                'em_atendimento'  => (int) $funil->em_atendimento,
                'encerrados_hoje' => (int) $funil->encerrados_hoje,
            ],
            'delivery' => [
                'pedidos'      => $delivPedidos,
                'receita'      => $this->brl($delivReceita),
                'ticket_medio' => $this->brl($delivPedidos > 0 ? $delivReceita / $delivPedidos : 0),
            ],
            'delivery_historico' => array_map(fn ($r) => [
                'label'   => date('d/m', strtotime($r->dia)),
                'value'   => (float) $r->total,
                'display' => $this->brl((float) $r->total),
            ], $delivHistorico),
        ];
    }

    private function getMenuSummary(): array
    {
        $counts = DB::selectOne("
            SELECT
                COUNT(CASE WHEN active = 1 THEN 1 END) AS ativos,
                COUNT(CASE WHEN active = 0 THEN 1 END) AS inativos
            FROM dishes
        ");

        $categorias = DB::select("
            SELECT dc.name, COUNT(d.id) AS qtd
            FROM dish_categories dc
            LEFT JOIN dishes d ON d.category_id = dc.id AND d.active = 1
            GROUP BY dc.id, dc.name
            ORDER BY qtd DESC
        ");

        return [
            'ativos'    => (int) ($counts->ativos ?? 0),
            'inativos'  => (int) ($counts->inativos ?? 0),
            'categorias'=> array_map(fn ($r) => [
                'label'  => (string) $r->name,
                'value'  => (int) $r->qtd,
                'display'=> (string) $r->qtd,
            ], $categorias),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolvePeriod(Request $request): array
    {
        // Datas explícitas têm prioridade (usadas pelo filtro de intervalo do dashboard)
        if ($request->has('date_from') || $request->has('date_to')) {
            $from = (string) $request->query('date_from', now()->toDateString());
            $to   = (string) $request->query('date_to',   now()->toDateString());
            if ($from > $to) [$from, $to] = [$to, $from];

            return ['from' => $from, 'to' => $to, 'preset' => null];
        }

        $preset = $request->query('period', 'today');

        return match ($preset) {
            '7d'  => ['from' => now()->subDays(6)->toDateString(), 'to' => now()->toDateString(), 'preset' => '7d'],
            '30d' => ['from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString(), 'preset' => '30d'],
            default => ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'preset' => 'today'],
        };
    }

    private function brl(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function formatDatePtBr(): string
    {
        $months = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
        $now = now();

        return sprintf('%s de %s de %s', $now->format('d'), $months[(int) $now->format('n')], $now->format('Y'));
    }
}
