<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TablesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Tables', $this->indexPayload());
    }

    public function indexOperacional(): Response
    {
        return Inertia::render('Admin/TablesKanban', $this->indexPayload());
    }

    private function indexPayload(): array
    {
        $tables = DB::table('tables')->orderBy('number')->get();

        $tableData = $tables->map(function (object $table): array {
            $session = DB::table('table_sessions')
                ->where('table_id', $table->id)
                ->whereNull('closed_at')
                ->first();

            if (! (bool) $table->active) {
                return $this->buildTablePayload($table, 'inactive', null, 0.0, null, 0);
            }

            if (! $session) {
                return $this->buildTablePayload($table, 'available', null, 0.0, null, 0);
            }

            $ordersCount = (int) DB::table('orders')
                ->where('table_session_id', $session->id)
                ->count();

            $unpaidOrdersCount = (int) DB::table('orders')
                ->where('table_session_id', $session->id)
                ->where('paid', false)
                ->count();

            $unpaidTotal = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.table_session_id', $session->id)
                ->where('orders.paid', false)
                ->sum(DB::raw('order_items.quantity * order_items.unit_price'));

            if ($ordersCount === 0) {
                $status = 'in_use';
            } elseif ($unpaidOrdersCount === 0) {
                $status = 'pending_release';
            } else {
                $status = 'open_bill';
            }

            $unpaidFloat = (float) $unpaidTotal;
            $totalOpen   = $unpaidFloat > 0
                ? 'R$ ' . number_format($unpaidFloat, 2, ',', '.')
                : null;

            return $this->buildTablePayload(
                $table,
                $status,
                $totalOpen,
                $unpaidFloat,
                $session->started_at,
                $ordersCount,
                $session->id,
                $this->getSessionCancelledOrders($session->id),
            );
        })->values()->all();

        $stats = [
            'total'     => count($tableData),
            'available' => count(array_filter($tableData, fn ($t) => $t['status'] === 'available')),
            'in_use'    => count(array_filter($tableData, fn ($t) => in_array($t['status'], ['in_use', 'open_bill', 'pending_release']))),
        ];

        return [
            'tables' => $tableData,
            'stats'  => $stats,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:99', 'unique:tables,number'],
            'label'  => ['nullable', 'string', 'max:80'],
            'active' => ['boolean'],
        ], [
            'number.unique' => 'Já existe uma mesa com este número.',
        ]);

        DB::table('tables')->insert([
            'id'         => (string) Str::uuid(),
            'number'     => $validated['number'],
            'label'      => isset($validated['label']) && trim($validated['label']) !== ''
                ? trim($validated['label'])
                : 'Mesa ' . $validated['number'],
            'active'     => $request->boolean('active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa cadastrada com sucesso.');
    }

    public function update(Request $request, string $table): RedirectResponse
    {
        $existing = DB::table('tables')->where('id', $table)->first();
        abort_unless($existing, 404);

        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:99', "unique:tables,number,{$table},id"],
            'label'  => ['nullable', 'string', 'max:80'],
            'active' => ['boolean'],
        ], [
            'number.unique' => 'Já existe uma mesa com este número.',
        ]);

        DB::table('tables')->where('id', $table)->update([
            'number'     => $validated['number'],
            'label'      => isset($validated['label']) && trim($validated['label']) !== ''
                ? trim($validated['label'])
                : 'Mesa ' . $validated['number'],
            'active'     => $request->boolean('active', true),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa atualizada com sucesso.');
    }

    public function destroy(string $table): RedirectResponse
    {
        $existing = DB::table('tables')->where('id', $table)->first();
        abort_unless($existing, 404);

        $hasOpenSession = DB::table('table_sessions')
            ->where('table_id', $table)
            ->whereNull('closed_at')
            ->exists();

        if ($hasOpenSession) {
            return back()->withErrors(['delete' => 'Encerre a sessão antes de excluir a mesa.']);
        }

        $hasOrders = DB::table('orders')->where('table_id', $table)->exists();

        if ($hasOrders) {
            return back()->withErrors(['delete' => 'Mesa com pedidos não pode ser excluída. Use a opção Desativar.']);
        }

        DB::table('tables')->where('id', $table)->delete();

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa excluída com sucesso.');
    }

    public function release(string $table): RedirectResponse
    {
        $existing = DB::table('tables')->where('id', $table)->first();
        abort_unless($existing, 404);

        $session = DB::table('table_sessions')
            ->where('table_id', $table)
            ->whereNull('closed_at')
            ->first();

        if (! $session) {
            return back()->withErrors(['release' => 'Esta mesa não possui sessão ativa.']);
        }

        DB::transaction(function () use ($session) {
            $now = now();

            DB::table('orders')
                ->where('table_session_id', $session->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'updated_at' => $now]);

            DB::table('table_sessions')->where('id', $session->id)->update([
                'closed_at'     => $now,
                'closed_reason' => 'admin_release',
                'updated_at'    => $now,
            ]);
        });

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa liberada com sucesso.');
    }

    public function closeAccount(Request $request, string $table): RedirectResponse
    {
        $existing = DB::table('tables')->where('id', $table)->first();
        abort_unless($existing, 404);

        $validated = $request->validate([
            'payment_method'  => ['required', 'in:dinheiro,pix,debito,credito,misto'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tip_amount'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $session = DB::table('table_sessions')
            ->where('table_id', $table)
            ->whereNull('closed_at')
            ->first();

        if (! $session) {
            return back()->withErrors(['close_account' => 'Esta mesa não possui sessão ativa.']);
        }

        DB::transaction(function () use ($session, $validated) {
            $now = now();

            DB::table('orders')
                ->where('table_session_id', $session->id)
                ->where('paid', false)
                ->update(['paid' => true, 'updated_at' => $now]);

            DB::table('table_sessions')->where('id', $session->id)->update([
                'closed_at'       => $now,
                'closed_reason'   => 'paid',
                'payment_method'  => $validated['payment_method'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tip_amount'      => $validated['tip_amount'] ?? 0,
                'updated_at'      => $now,
            ]);
        });

        return redirect()->route('admin.mesas.index')->with('success', 'Conta fechada e mesa liberada.');
    }

    private function buildTablePayload(
        object $table,
        string $status,
        ?string $totalOpen,
        float $totalOpenRaw,
        ?string $sessionStartedAt,
        int $ordersCount,
        ?string $sessionId = null,
        array $cancelledOrders = [],
    ): array {
        return [
            'id'                 => $table->id,
            'number'             => (int) $table->number,
            'label'              => $table->label ?? 'Mesa ' . $table->number,
            'active'             => (bool) $table->active,
            'status'             => $status,
            'total_open'         => $totalOpen,
            'total_open_raw'     => $totalOpenRaw,
            'session_id'         => $sessionId,
            'session_started_at' => $sessionStartedAt,
            'orders_count'       => $ordersCount,
            'cancelled_orders'   => $cancelledOrders,
        ];
    }

    /**
     * Pedidos cancelados (na mesa/kanban) da sessão aberta, para exibir no fechamento de conta.
     */
    private function getSessionCancelledOrders(string $sessionId): array
    {
        $rows = DB::select("
            SELECT
                orders.id,
                orders.cancel_reason,
                orders.updated_at,
                order_items.quantity,
                dishes.name AS dish_name
            FROM orders
            INNER JOIN order_items ON order_items.order_id = orders.id
            INNER JOIN dishes ON dishes.id = order_items.dish_id
            WHERE orders.table_session_id = :session_id
              AND orders.status = 'cancelled'
            ORDER BY orders.updated_at DESC, orders.id
        ", ['session_id' => $sessionId]);

        $grouped = [];
        foreach ($rows as $row) {
            $uuid = $row->id;
            if (! isset($grouped[$uuid])) {
                $grouped[$uuid] = [
                    'id'     => substr(str_replace('-', '', $uuid), 0, 4),
                    'reason' => $row->cancel_reason,
                    'time'   => date('H:i', strtotime($row->updated_at)),
                    'items'  => [],
                ];
            }
            $grouped[$uuid]['items'][] = [
                'qty'  => (int) $row->quantity,
                'name' => (string) $row->dish_name,
            ];
        }

        return array_values($grouped);
    }
}
