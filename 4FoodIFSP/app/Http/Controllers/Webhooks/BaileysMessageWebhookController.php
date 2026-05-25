<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\IncomingWhatsAppMessageService;
use Illuminate\Http\Request;

class BaileysMessageWebhookController extends Controller
{
    public function handle(Request $request, IncomingWhatsAppMessageService $service)
    {
        $secret       = $request->header('X-Baileys-Secret');
        $configSecret = config('services.baileys.webhook_secret');
        abort_unless($secret && $configSecret && hash_equals($configSecret, $secret), 401);

        $validated = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'wa_message_id' => ['required', 'string', 'max:128'],
            'phone_number'  => ['required', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'body'          => ['required', 'string', 'max:5000'],
            'sent_at'       => ['nullable', 'date'],
        ]);

        $service->handleInbound($validated);

        return response()->json(['ok' => true]);
    }
}
