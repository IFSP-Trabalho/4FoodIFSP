<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\IncomingWhatsAppMessageService;
use Illuminate\Http\Request;

class BaileysMessageWebhookController extends Controller
{
    public function handle(Request $request, IncomingWhatsAppMessageService $service)
    {
        $secret = $request->header('X-Baileys-Secret');
        abort_unless($secret && hash_equals(config('services.baileys.webhook_secret'), $secret), 401);

        $validated = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'wa_message_id' => ['required', 'string', 'max:128'],
            'phone_number'  => ['required', 'string', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'body'          => ['required', 'string', 'max:5000'],
            'sent_at'       => ['nullable', 'date'],
        ]);

        $service->handleInbound($validated);

        return response()->json(['ok' => true]);
    }
}
