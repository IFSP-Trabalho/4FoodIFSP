<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function vendas(Request $request): Response
    {
        [$from, $to] = $this->resolvePeriod($request);

        $totais = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN oi.unit_price * oi.quantity END), 0) AS faturamento,
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' AND o.paid = 1 THEN oi.unit_price * oi.quantity END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' AND o.paid = 0 THEN oi.unit_price * oi.quantity END), 0) AS pendente,
                COUNT(DISTINCT CASE WHEN o.status <> 'cancelled' THEN o.id END) AS pedidos,
                COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) AS cancelados
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN :from AND :to
        ", ['from' => $from, 'to' => $to]);

        $faturamento = (float) $totais->faturamento;
        $pedidos = (int) $totais->pedidos;

        $porDia = DB::select("
            SELECT DATE(o.created_at) AS dia,
                   SUM(oi.unit_price * oi.quantity) AS total,
                   COUNT(DISTINCT o.id) AS pedidos
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY DATE(o.created_at)
            ORDER BY dia
        ", ['from' => $from, 'to' => $to]);

        $topPratos = DB::select("
            SELECT d.name,
                   SUM(oi.quantity) AS qtd,
                   SUM(oi.unit_price * oi.quantity) AS total
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN dishes d ON d.id = oi.dish_id
            WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY d.id, d.name
            ORDER BY total DESC
            LIMIT 8
        ", ['from' => $from, 'to' => $to]);

        $pagamentos = DB::select("
            SELECT payment_method AS metodo, COUNT(*) AS qtd
            FROM table_sessions
            WHERE closed_at IS NOT NULL
              AND payment_method IS NOT NULL
              AND DATE(closed_at) BETWEEN :from AND :to
            GROUP BY payment_method
            ORDER BY qtd DESC
        ", ['from' => $from, 'to' => $to]);

        $pagamentoLabels = [
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'debito' => 'Débito',
            'credito' => 'Crédito',
            'misto' => 'Misto',
        ];

        return Inertia::render('Reports/Vendas', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'date' => $this->formatDatePtBr(),
            'kpis' => [
                'faturamento' => $this->brl($faturamento),
                'recebido' => $this->brl((float) $totais->recebido),
                'pendente' => $this->brl((float) $totais->pendente),
                'pedidos' => $pedidos,
                'cancelados' => (int) $totais->cancelados,
                'ticket_medio' => $this->brl($pedidos > 0 ? $faturamento / $pedidos : 0),
            ],
            'por_dia' => array_map(fn ($r) => [
                'dia' => date('d/m', strtotime($r->dia)),
                'total' => (float) $r->total,
                'total_label' => $this->brl((float) $r->total),
                'pedidos' => (int) $r->pedidos,
            ], $porDia),
            'top_pratos' => array_map(fn ($r) => [
                'nome' => (string) $r->name,
                'qtd' => (int) $r->qtd,
                'total' => (float) $r->total,
                'total_label' => $this->brl((float) $r->total),
            ], $topPratos),
            'pagamentos' => array_map(fn ($r) => [
                'metodo' => $pagamentoLabels[$r->metodo] ?? (string) $r->metodo,
                'qtd' => (int) $r->qtd,
            ], $pagamentos),
        ]);
    }

    public function cozinha(Request $request): Response
    {
        [$from, $to] = $this->resolvePeriod($request);

        $totais = DB::selectOne("
            SELECT
                COUNT(DISTINCT CASE WHEN status = 'ready' THEN id END) AS preparados,
                COUNT(DISTINCT CASE WHEN status = 'cancelled' THEN id END) AS cancelados,
                AVG(CASE WHEN status = 'ready' THEN TIMESTAMPDIFF(MINUTE, created_at, updated_at) END) AS tempo_medio
            FROM orders
            WHERE DATE(created_at) BETWEEN :from AND :to
        ", ['from' => $from, 'to' => $to]);

        $emPreparoAgora = (int) DB::table('orders')
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $topPratos = DB::select("
            SELECT d.name, SUM(oi.quantity) AS qtd
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN dishes d ON d.id = oi.dish_id
            WHERE o.status = 'ready' AND DATE(o.created_at) BETWEEN :from AND :to
            GROUP BY d.id, d.name
            ORDER BY qtd DESC
            LIMIT 8
        ", ['from' => $from, 'to' => $to]);

        $cancelamentos = DB::select("
            SELECT COALESCE(NULLIF(TRIM(cancel_reason), ''), 'Sem motivo informado') AS motivo,
                   COUNT(*) AS qtd
            FROM orders
            WHERE status = 'cancelled' AND DATE(created_at) BETWEEN :from AND :to
            GROUP BY motivo
            ORDER BY qtd DESC
        ", ['from' => $from, 'to' => $to]);

        $tempoMedio = $totais->tempo_medio !== null ? round((float) $totais->tempo_medio) : null;

        return Inertia::render('Reports/Cozinha', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'date' => $this->formatDatePtBr(),
            'kpis' => [
                'preparados' => (int) $totais->preparados,
                'cancelados' => (int) $totais->cancelados,
                'tempo_medio' => $tempoMedio !== null ? "{$tempoMedio} min" : '—',
                'em_preparo' => $emPreparoAgora,
            ],
            'top_pratos' => array_map(fn ($r) => [
                'nome' => (string) $r->name,
                'qtd' => (int) $r->qtd,
            ], $topPratos),
            'cancelamentos' => array_map(fn ($r) => [
                'motivo' => (string) $r->motivo,
                'qtd' => (int) $r->qtd,
            ], $cancelamentos),
        ]);
    }

    public function garcom(Request $request): Response
    {
        [$from, $to] = $this->resolvePeriod($request);

        $totais = DB::selectOne("
            SELECT
                COUNT(*) AS mesas_atendidas,
                COUNT(CASE WHEN closed_at IS NOT NULL THEN 1 END) AS contas_fechadas,
                COUNT(CASE WHEN closed_reason = 'paid' THEN 1 END) AS fechadas_pagas,
                AVG(CASE WHEN closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, started_at, closed_at) END) AS tempo_medio
            FROM table_sessions
            WHERE DATE(started_at) BETWEEN :from AND :to
        ", ['from' => $from, 'to' => $to]);

        $faturamento = (float) DB::selectOne("
            SELECT COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            INNER JOIN table_sessions ts ON ts.id = o.table_session_id
            WHERE o.status <> 'cancelled' AND DATE(ts.started_at) BETWEEN :from AND :to
        ", ['from' => $from, 'to' => $to])->total;

        $porMesa = DB::select("
            SELECT COALESCE(t.label, CONCAT('Mesa ', t.number)) AS mesa,
                   COUNT(DISTINCT ts.id) AS sessoes,
                   COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS total
            FROM table_sessions ts
            INNER JOIN tables t ON t.id = ts.table_id
            LEFT JOIN orders o ON o.table_session_id = ts.id AND o.status <> 'cancelled'
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE DATE(ts.started_at) BETWEEN :from AND :to
            GROUP BY t.id, t.number, t.label
            ORDER BY total DESC
            LIMIT 12
        ", ['from' => $from, 'to' => $to]);

        $contas = (int) $totais->contas_fechadas;
        $tempoMedio = $totais->tempo_medio !== null ? round((float) $totais->tempo_medio) : null;

        return Inertia::render('Reports/Garcom', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'date' => $this->formatDatePtBr(),
            'kpis' => [
                'mesas_atendidas' => (int) $totais->mesas_atendidas,
                'contas_fechadas' => $contas,
                'faturamento' => $this->brl($faturamento),
                'ticket_medio' => $this->brl($contas > 0 ? $faturamento / $contas : 0),
                'tempo_medio' => $tempoMedio !== null ? "{$tempoMedio} min" : '—',
            ],
            'por_mesa' => array_map(fn ($r) => [
                'mesa' => (string) $r->mesa,
                'sessoes' => (int) $r->sessoes,
                'total' => (float) $r->total,
                'total_label' => $this->brl((float) $r->total),
            ], $porMesa),
        ]);
    }

    public function whatsapp(Request $request): Response
    {
        [$from, $to] = $this->resolvePeriod($request);

        $triagem = (int) DB::table('wa_tickets')->where('status', 'triage')->count();
        $emAndamento = (int) DB::table('wa_tickets')->where('status', 'in_progress')->count();

        $fechados = (int) DB::table('wa_tickets')
            ->where('status', 'closed')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$from, $to])
            ->count();

        $pedidosDelivery = (int) DB::table('orders')
            ->where('origin', 'delivery')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->count();

        $motivos = DB::select("
            SELECT COALESCE(r.name, 'Sem motivo') AS motivo, COUNT(*) AS qtd
            FROM wa_tickets t
            LEFT JOIN wa_closure_reasons r ON r.id = t.closure_reason_id
            WHERE t.status = 'closed' AND DATE(t.updated_at) BETWEEN :from AND :to
            GROUP BY motivo
            ORDER BY qtd DESC
        ", ['from' => $from, 'to' => $to]);

        $porDia = DB::select("
            SELECT DATE(created_at) AS dia, COUNT(*) AS qtd
            FROM wa_tickets
            WHERE DATE(created_at) BETWEEN :from AND :to
            GROUP BY DATE(created_at)
            ORDER BY dia
        ", ['from' => $from, 'to' => $to]);

        return Inertia::render('Reports/WhatsApp', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'date' => $this->formatDatePtBr(),
            'kpis' => [
                'triagem' => $triagem,
                'em_andamento' => $emAndamento,
                'fechados' => $fechados,
                'pedidos_delivery' => $pedidosDelivery,
            ],
            'motivos' => array_map(fn ($r) => [
                'motivo' => (string) $r->motivo,
                'qtd' => (int) $r->qtd,
            ], $motivos),
            'por_dia' => array_map(fn ($r) => [
                'dia' => date('d/m', strtotime($r->dia)),
                'qtd' => (int) $r->qtd,
            ], $porDia),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->query('date_from', now()->toDateString());
        $to = $request->query('date_to', now()->toDateString());

        // Garante from <= to para não devolver intervalo vazio.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [(string) $from, (string) $to];
    }

    private function brl(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function formatDatePtBr(): string
    {
        $months = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        $now = now();

        return sprintf('%s de %s de %s', $now->format('d'), $months[(int) $now->format('n')], $now->format('Y'));
    }
}
