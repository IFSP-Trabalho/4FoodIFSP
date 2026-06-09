<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutboundWhatsAppMessageService
{
    /**
     * Envia texto pela ponte Baileys e grava a wa_message outbound no ticket.
     *
     * Reaproveita o padrão do InboxController::send (sem o contexto de auth/HTTP).
     * Retorna false se não houver conexão conectada ou se o envio falhar — na
     * Fase 1 o motor apenas loga e segue (não estoura 500 no webhook).
     *
     * @param  string       $waTicketId    ticket onde a resposta será anexada
     * @param  string|null  $connectionId  conexão preferida (a que recebeu a mensagem)
     * @param  string       $to            destino (JID / número do cliente)
     * @param  string       $body          texto a enviar
     */
    public function send(string $waTicketId, ?string $connectionId, string $to, string $body): bool
    {
        $connection = $connectionId
            ? DB::table('wa_connections')->where('id', $connectionId)->first()
            : null;

        // Fallback para a conexão conectada mais recente quando a preferida não
        // está utilizável (mesma lógica do InboxController::send).
        if (! $connection || $connection->connection_status !== 'connected' || ! $connection->baileys_session_id) {
            $connection = DB::table('wa_connections')
                ->where('connection_status', 'connected')
                ->whereNotNull('baileys_session_id')
                ->orderByDesc('last_status_at')
                ->first();
        }

        if (! $connection || ! $connection->baileys_session_id) {
            Log::warning('ChatbotEngine: nenhuma conexão WhatsApp conectada para envio outbound', [
                'wa_ticket_id' => $waTicketId,
            ]);

            return false;
        }

        $baileysUrl = rtrim(config('services.baileys.url', 'http://127.0.0.1:3001'), '/');

        try {
            $response = Http::post("{$baileysUrl}/sessions/{$connection->baileys_session_id}/send", [
                'to'   => $to,
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChatbotEngine: falha ao chamar a ponte Baileys', [
                'wa_ticket_id' => $waTicketId,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('ChatbotEngine: ponte Baileys retornou erro no envio', [
                'wa_ticket_id' => $waTicketId,
                'status'       => $response->status(),
                'body'         => $response->body(),
            ]);

            return false;
        }

        $now = now();

        DB::table('wa_messages')->insert([
            'id'            => (string) Str::uuid(),
            'wa_ticket_id'  => $waTicketId,
            'direction'     => 'outbound',
            'body'          => $body,
            'wa_message_id' => null,
            'sent_at'       => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $update = ['updated_at' => $now];
        if ((string) ($connection->id) !== (string) $connectionId) {
            $update['wa_connection_id'] = $connection->id;
        }
        DB::table('wa_tickets')->where('id', $waTicketId)->update($update);

        return true;
    }
}
