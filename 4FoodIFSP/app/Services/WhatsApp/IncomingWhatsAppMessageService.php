<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncomingWhatsAppMessageService
{
    public function handleInbound(array $data): void
    {
        DB::transaction(function () use ($data) {
            if (DB::table('wa_messages')->where('wa_message_id', $data['wa_message_id'])->exists()) {
                return;
            }

            // Busca pelo JID completo OU pelo número local (compatibilidade com formato antigo)
            $localPart = explode('@', $data['phone_number'])[0];

            $openTicket = DB::table('wa_tickets')
                ->where(function ($q) use ($data, $localPart) {
                    $q->where('phone_number', $data['phone_number'])
                      ->orWhere('phone_number', $localPart);
                })
                ->whereIn('status', ['triage', 'in_progress'])
                ->orderByDesc('updated_at')
                ->first();

            if ($openTicket) {
                $ticketId = $openTicket->id;

                $ticketUpdate = [];

                // Migra phone_number para o formato JID completo
                if ($openTicket->phone_number !== $data['phone_number']) {
                    $ticketUpdate['phone_number'] = $data['phone_number'];
                }

                if (empty($openTicket->customer_name) && ! empty($data['customer_name'])) {
                    $ticketUpdate['customer_name'] = $data['customer_name'];
                }

                if (empty($openTicket->wa_connection_id) && ! empty($data['connection_id'])) {
                    $ticketUpdate['wa_connection_id'] = $data['connection_id'];
                }

                if (! empty($ticketUpdate)) {
                    DB::table('wa_tickets')->where('id', $ticketId)->update($ticketUpdate);
                }
            } else {
                $ticketId = (string) Str::uuid();
                DB::table('wa_tickets')->insert([
                    'id'               => $ticketId,
                    'wa_connection_id' => $data['connection_id'],
                    'phone_number'     => $data['phone_number'],
                    'customer_name'    => $data['customer_name'] ?? null,
                    'status'           => 'triage',
                    'agent_id'         => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $sentAt = isset($data['sent_at'])
                ? Carbon::parse($data['sent_at'])->toDateTimeString()
                : now()->toDateTimeString();

            DB::table('wa_messages')->insert([
                'id'            => (string) Str::uuid(),
                'wa_ticket_id'  => $ticketId,
                'direction'     => 'inbound',
                'body'          => $data['body'],
                'wa_message_id' => $data['wa_message_id'],
                'sent_at'       => $sentAt,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $ticketFinalUpdate = ['updated_at' => now()];
            if ($openTicket && $openTicket->status === 'in_progress') {
                $ticketFinalUpdate['is_unread']    = true;
                $ticketFinalUpdate['unread_count'] = DB::raw('unread_count + 1');
            }
            DB::table('wa_tickets')->where('id', $ticketId)->update($ticketFinalUpdate);
        });
    }
}
