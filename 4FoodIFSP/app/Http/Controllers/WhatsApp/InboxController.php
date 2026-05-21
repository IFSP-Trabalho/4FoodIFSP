<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WhatsApp/Inbox', [
            'tickets' => $this->getTickets(),
            'date'    => now()->format('d/m/Y'),
        ]);
    }

    private function getTickets(): array
    {
        return [
            'in_progress' => [
                [
                    'id'            => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                    'customer_name' => 'Maria Silva',
                    'phone_number'  => '+5511999887766',
                    'last_message'  => 'Pode confirmar se o pedido já saiu?',
                    'updated_at'    => now()->subMinutes(12)->toIso8601String(),
                ],
                [
                    'id'            => 'b2c3d4e5-f6a7-8901-bcde-f12345678901',
                    'customer_name' => 'Carlos Mendes',
                    'phone_number'  => '+5511988776655',
                    'last_message'  => 'Quero alterar o endereço de entrega',
                    'updated_at'    => now()->subHour()->toIso8601String(),
                ],
            ],
            'triage' => [
                [
                    'id'            => 'c3d4e5f6-a7b8-9012-cdef-123456789012',
                    'customer_name' => 'Ana Costa',
                    'phone_number'  => '+5511977665544',
                    'last_message'  => 'Oi, quero fazer um pedido de delivery',
                    'updated_at'    => now()->subMinutes(3)->toIso8601String(),
                ],
                [
                    'id'            => 'd4e5f6a7-b8c9-0123-defa-234567890123',
                    'customer_name' => null,
                    'phone_number'  => '+5511966554433',
                    'last_message'  => 'Vocês estão abertos agora?',
                    'updated_at'    => now()->subMinutes(25)->toIso8601String(),
                ],
                [
                    'id'            => 'e5f6a7b8-c9d0-1234-efab-345678901234',
                    'customer_name' => 'Pedro Lima',
                    'phone_number'  => '+5511955443322',
                    'last_message'  => 'Tem promoção hoje?',
                    'updated_at'    => now()->subHours(2)->toIso8601String(),
                ],
            ],
            'closed' => [
                [
                    'id'            => 'f6a7b8c9-d0e1-2345-fabc-456789012345',
                    'customer_name' => 'Juliana Rocha',
                    'phone_number'  => '+5511944332211',
                    'last_message'  => 'Obrigada! Pedido recebido.',
                    'updated_at'    => now()->subHours(4)->toIso8601String(),
                ],
            ],
        ];
    }
}
