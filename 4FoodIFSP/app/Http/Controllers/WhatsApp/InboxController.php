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
            'tickets'    => $this->buildTicketsGrouped(),
            'date'       => now()->format('d/m/Y'),
            'authUserId' => auth()->id(),
        ]);
    }

    public function poll()
    {
        return response()->json([
            'triage_ids'      => DB::table('wa_tickets')->where('status', 'triage')->orderBy('created_at')->pluck('id'),
            'in_progress_ids' => DB::table('wa_tickets')->where('status', 'in_progress')->orderByDesc('updated_at')->pluck('id'),
            'updated_at_max'  => DB::table('wa_tickets')->max('updated_at'),
            'server_time'     => now()->toIso8601String(),
        ]);
    }

    public function messages(string $ticket)
    {
        $record = DB::table('wa_tickets')->where('id', $ticket)->first();
        abort_unless($record, 404);

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
        abort_unless($record->wa_connection_id, 422, 'Conexão WhatsApp não vinculada ao ticket.');

        $connection = DB::table('wa_connections')->where('id', (string) $record->wa_connection_id)->first();
        abort_unless($connection, 422, 'Conexão WhatsApp não encontrada.');
        abort_unless($connection->baileys_session_id, 422, 'Sessão Baileys não configurada na conexão.');

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
        ];

        return [
            'triage'      => $rows->where('status', 'triage')->sortBy('created_at')->values()->map($format)->values()->all(),
            'in_progress' => $rows->where('status', 'in_progress')->sortByDesc('updated_at')->values()->map($format)->values()->all(),
            'closed'      => $rows->where('status', 'closed')->sortByDesc('updated_at')->values()->map($format)->values()->all(),
        ];
    }
}
