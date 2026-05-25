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

            $openTicket = DB::table('wa_tickets')
                ->where('phone_number', $data['phone_number'])
                ->whereIn('status', ['triage', 'in_progress'])
                ->orderByDesc('updated_at')
                ->first();

            if ($openTicket) {
                $ticketId = $openTicket->id;

                if (empty($openTicket->customer_name) && ! empty($data['customer_name'])) {
                    DB::table('wa_tickets')->where('id', $ticketId)->update([
                        'customer_name' => $data['customer_name'],
                    ]);
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

            DB::table('wa_tickets')->where('id', $ticketId)->update(['updated_at' => now()]);
        });
    }
}
