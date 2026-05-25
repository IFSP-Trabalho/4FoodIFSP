<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BaileysWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = $request->header('X-Baileys-Secret');
        $configSecret = config('services.baileys.webhook_secret');

        abort_unless($secret && $configSecret && hash_equals($configSecret, $secret), 401);

        $validated = $request->validate([
            'connection_id' => ['required', 'uuid'],
            'status'        => ['required', 'in:connected,disconnected,pairing'],
            'phone_number'  => ['nullable', 'string', 'max:20'],
        ]);

        $update = [
            'connection_status' => $validated['status'],
            'last_status_at'    => now(),
            'updated_at'        => now(),
        ];

        if ($validated['status'] === 'connected' && ! empty($validated['phone_number'])) {
            $update['phone_number'] = $validated['phone_number'];
        }

        if ($validated['status'] === 'disconnected') {
            $update['phone_number'] = null;
        }

        DB::table('wa_connections')
            ->where('id', $validated['connection_id'])
            ->update($update);

        return response()->json(['ok' => true]);
    }
}
