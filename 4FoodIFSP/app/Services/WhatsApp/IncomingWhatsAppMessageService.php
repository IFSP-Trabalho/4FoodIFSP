<?php

namespace App\Services\WhatsApp;

use App\Support\WaPhone;
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

            $this->ensureContact($data);

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

    /**
     * Insere o contato automaticamente na primeira mensagem recebida.
     * Se já existir (mesmo número nacional), nada é atualizado — o nome
     * permanece fixo a partir da primeira inserção.
     */
    private function ensureContact(array $data): void
    {
        $national = WaPhone::national($data['phone_number']);

        if ($national === '') {
            return;
        }

        if (DB::table('wa_contacts')->where('phone_digits', $national)->exists()) {
            return;
        }

        [$ddd, $number] = WaPhone::split($data['phone_number']);

        DB::table('wa_contacts')->insert([
            'id'           => (string) Str::uuid(),
            'name'         => $data['customer_name'] ?? null,
            'phone_number' => $data['phone_number'],
            'country_code' => WaPhone::country($data['phone_number']),
            'phone_digits' => $national,
            'ddd'          => $ddd,
            'number'       => $number,
            'cpf'          => null,
            'notes'        => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
