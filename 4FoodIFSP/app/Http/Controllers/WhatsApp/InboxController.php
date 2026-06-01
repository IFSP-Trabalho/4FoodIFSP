<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WhatsApp/Inbox', [
            'tickets'        => $this->buildTicketsGrouped(),
            'date'           => now()->format('d/m/Y'),
            'authUserId'     => auth()->id(),
            'focusTicketId'  => request()->query('ticket'),
            'closureReasons' => DB::table('wa_closure_reasons')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($r) => ['id' => (string) $r->id, 'name' => (string) $r->name])
                ->all(),
        ]);
    }

    public function poll()
    {
        return response()->json([
            'triage_ids'      => DB::table('wa_tickets')->where('status', 'triage')->orderBy('created_at')->pluck('id'),
            'in_progress_ids' => DB::table('wa_tickets')->where('status', 'in_progress')->orderByDesc('updated_at')->pluck('id'),
            'closed_ids'      => DB::table('wa_tickets')->where('status', 'closed')->whereDate('updated_at', today())->orderByDesc('updated_at')->pluck('id'),
            'unread_counts'   => DB::table('wa_tickets')->where('is_unread', true)->pluck('unread_count', 'id'),
            'updated_at_max'  => DB::table('wa_tickets')->max('updated_at'),
            'server_time'     => now()->toIso8601String(),
        ]);
    }

    public function messages(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);

        DB::table('wa_tickets')->where('id', $ticket)->where('is_unread', true)->update([
            'is_unread'    => false,
            'unread_count' => 0,
        ]);

        $messages = DB::table('wa_messages')
            ->where('wa_ticket_id', $ticket)
            ->orderByRaw('COALESCE(sent_at, created_at) ASC')
            ->get(['id', 'direction', 'body', 'sent_at'])
            ->map(fn($m) => [
                'id'        => $m->id,
                'direction' => $m->direction,
                'body'      => $m->body,
                'sent_at'   => $m->sent_at ? Carbon::parse($m->sent_at)->toIso8601String() : null,
            ])
            ->all();

        return response()->json([
            'ticket' => [
                'id'            => $record->id,
                'customer_name' => $record->customer_name,
                'phone_number'  => $record->phone_number,
                'status'        => $record->status,
            ],
            'messages' => $messages,
        ]);
    }

    public function send(string $ticket, Request $request)
    {
        $request->validate(['body' => 'required|string|max:4096']);

        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'in_progress', 422, 'Ticket não está em atendimento.');

        $connection = $record->wa_connection_id
            ? DB::table('wa_connections')->where('id', (string) $record->wa_connection_id)->first()
            : null;

        // Liga automaticamente a uma conexão conectada quando o ticket não tem
        // vínculo (ex.: atendimento aberto pela tela de Contatos antes de a
        // conexão subir) ou quando a conexão vinculada não está utilizável.
        if (! $connection || $connection->connection_status !== 'connected' || ! $connection->baileys_session_id) {
            $connection = DB::table('wa_connections')
                ->where('connection_status', 'connected')
                ->whereNotNull('baileys_session_id')
                ->orderByDesc('last_status_at')
                ->first();
        }

        abort_unless($connection, 422, 'Nenhuma conexão WhatsApp conectada disponível para envio.');
        abort_unless($connection->baileys_session_id, 422, 'Sessão Baileys não configurada na conexão.');

        if ((string) $record->wa_connection_id !== (string) $connection->id) {
            DB::table('wa_tickets')->where('id', $ticket)->update(['wa_connection_id' => $connection->id]);
        }

        $baileysUrl = rtrim(config('services.baileys.url', 'http://127.0.0.1:3001'), '/');
        $response = Http::post("{$baileysUrl}/sessions/{$connection->baileys_session_id}/send", [
            'to'   => $record->phone_number,
            'body' => $request->body,
        ]);

        abort_unless($response->successful(), 502, $response->json('message') ?? 'Falha ao enviar mensagem.');

        $messageId = (string) Str::uuid();
        $now = now();

        DB::table('wa_messages')->insert([
            'id'            => $messageId,
            'wa_ticket_id'  => $ticket,
            'direction'     => 'outbound',
            'body'          => $request->body,
            'wa_message_id' => null,
            'sent_at'       => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        DB::table('wa_tickets')->where('id', $ticket)->update(['updated_at' => $now]);

        return response()->json([
            'message' => [
                'id'        => $messageId,
                'direction' => 'outbound',
                'body'      => $request->body,
                'sent_at'   => $now->toIso8601String(),
            ],
        ]);
    }

    public function close(string $ticket, Request $request)
    {
        $request->validate([
            'closure_reason_id' => ['required', 'uuid', 'exists:wa_closure_reasons,id'],
            'summary'           => ['nullable', 'string', 'max:4096'],
        ], [
            'closure_reason_id.required' => 'Selecione um motivo de fechamento.',
            'closure_reason_id.exists'   => 'Motivo de fechamento inválido.',
        ]);

        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'in_progress', 422, 'Ticket não está em atendimento.');

        $reasonActive = DB::table('wa_closure_reasons')
            ->where('id', $request->closure_reason_id)
            ->where('active', true)
            ->exists();
        abort_unless($reasonActive, 422, 'Motivo de fechamento inativo.');

        DB::table('wa_tickets')->where('id', $ticket)->update([
            'status'            => 'closed',
            'closure_reason_id' => $request->closure_reason_id,
            'summary'           => $request->summary ?: null,
            'updated_at'        => now(),
        ]);

        return response()->json([
            'ok'     => true,
            'status' => 'closed',
            'id'     => $ticket,
        ]);
    }

    public function reopen(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'closed', 422, 'Ticket não está fechado.');

        DB::table('wa_tickets')->where('id', $ticket)->update([
            'status'     => 'in_progress',
            'agent_id'   => auth()->id(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok'     => true,
            'status' => 'in_progress',
            'id'     => $ticket,
        ]);
    }

    public function returnTriage(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'in_progress', 422, 'Ticket não está em atendimento.');

        DB::table('wa_tickets')->where('id', $ticket)->update([
            'status'     => 'triage',
            'agent_id'   => null,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => 'triage', 'id' => $ticket]);
    }

    public function markUnread(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if(!in_array($record->status, ['in_progress', 'closed']), 422, 'Ação não permitida.');

        DB::table('wa_tickets')->where('id', $ticket)->update([
            'is_unread'    => true,
            'unread_count' => DB::raw('GREATEST(unread_count, 1)'),
        ]);

        return response()->json(['ok' => true]);
    }

    public function accept(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);
        abort_if($record->status !== 'triage', 422, 'Ticket não está em triagem.');

        DB::table('wa_tickets')->where('id', $ticket)->update([
            'status'     => 'in_progress',
            'agent_id'   => auth()->id(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => 'in_progress']);
    }

    private function buildTicketsGrouped(): array
    {
        $rows = DB::table('wa_tickets as t')
            ->select([
                't.id',
                't.customer_name',
                't.phone_number',
                't.status',
                't.agent_id',
                't.is_unread',
                't.unread_count',
                't.updated_at',
                't.created_at',
                DB::raw('(
                    SELECT m.body FROM wa_messages m
                    WHERE m.wa_ticket_id = t.id
                    ORDER BY COALESCE(m.sent_at, m.created_at) DESC
                    LIMIT 1
                ) as last_message'),
            ])
            ->where(function ($q) {
                $q->whereIn('t.status', ['triage', 'in_progress'])
                  ->orWhere(function ($q2) {
                      $q2->where('t.status', 'closed')
                         ->whereDate('t.updated_at', today());
                  });
            })
            ->get();

        $format = fn($row) => [
            'id'            => $row->id,
            'customer_name' => $row->customer_name,
            'phone_number'  => $row->phone_number,
            'last_message'  => $row->last_message
                ? mb_strimwidth($row->last_message, 0, 60, '…')
                : null,
            'updated_at'    => $row->updated_at,
            'status'        => $row->status,
            'agent_id'      => $row->agent_id,
            'is_unread'     => (bool) $row->is_unread,
            'unread_count'  => (int) $row->unread_count,
        ];

        return [
            'triage'      => $rows->where('status', 'triage')->sortBy('created_at')->values()->map($format)->values()->all(),
            'in_progress' => $rows->where('status', 'in_progress')->sortByDesc('updated_at')->values()->map($format)->values()->all(),
            'closed'      => $rows->where('status', 'closed')->sortByDesc('updated_at')->values()->map($format)->values()->all(),
        ];
    }
}
