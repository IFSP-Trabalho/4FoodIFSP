<?php

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Exceptions\BaileysServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\BaileysClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConnectionsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/WhatsApp/Conexoes', [
            'connections' => DB::table('wa_connections')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($row) => [
                    'id'                => $row->id,
                    'name'              => $row->name,
                    'channel_type'      => $row->channel_type,
                    'phone_number'      => $row->phone_number,
                    'connection_status' => $row->connection_status,
                    'last_status_at'    => $row->last_status_at,
                    'updated_at'        => $row->updated_at,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'min:2', 'max:80', 'unique:wa_connections,name'],
            'channel_type' => ['required', 'string', 'in:whatsapp'],
        ], [
            'name.unique' => 'Já existe uma conexão com este nome.',
        ]);

        DB::table('wa_connections')->insert([
            'id'                => Str::uuid(),
            'name'              => trim($validated['name']),
            'channel_type'      => $validated['channel_type'],
            'connection_status' => 'disconnected',
            'last_status_at'    => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return redirect()->route('admin.whatsapp.conexoes.index')
            ->with('success', 'Conexão criada com sucesso.');
    }

    public function requestQr(string $connection, BaileysClient $baileys)
    {
        $record = DB::table('wa_connections')->where('id', $connection)->first();
        abort_unless($record, 404);

        if ($record->connection_status === 'connected') {
            return response()->json(['message' => 'Conexão já está ativa.'], 409);
        }

        try {
            $sessionId = $record->baileys_session_id ?? $record->id;

            if (! $record->baileys_session_id) {
                $baileys->createSession($record->id);
                DB::table('wa_connections')->where('id', $connection)->update([
                    'baileys_session_id' => $record->id,
                    'updated_at'         => now(),
                ]);
            }

            $result = $baileys->startSession($sessionId);

            if (($result['status'] ?? '') === 'connected') {
                DB::table('wa_connections')->where('id', $connection)->update([
                    'connection_status' => 'connected',
                    'phone_number'      => $result['phone_number'] ?? null,
                    'last_status_at'    => now(),
                    'updated_at'        => now(),
                ]);

                return response()->json([
                    'connection_status' => 'connected',
                    'phone_number'      => $result['phone_number'] ?? null,
                    'qr_base64'         => null,
                ]);
            }

            DB::table('wa_connections')->where('id', $connection)->update([
                'connection_status' => 'pairing',
                'last_status_at'    => now(),
                'updated_at'        => now(),
            ]);

            return response()->json([
                'connection_status' => 'pairing',
                'qr_base64'         => $result['qr_base64'] ?? null,
                'expires_at'        => $result['expires_at'] ?? null,
            ]);
        } catch (BaileysServiceUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function status(string $connection, BaileysClient $baileys)
    {
        $record = DB::table('wa_connections')->where('id', $connection)->first();
        abort_unless($record, 404);

        $qrBase64  = null;
        $expiresAt = null;

        if ($record->connection_status === 'pairing' && $record->baileys_session_id) {
            try {
                $live = $baileys->getStatus($record->baileys_session_id);

                if (($live['status'] ?? '') === 'connected' && $record->connection_status !== 'connected') {
                    DB::table('wa_connections')->where('id', $connection)->update([
                        'connection_status' => 'connected',
                        'phone_number'      => $live['phone_number'] ?? null,
                        'last_status_at'    => now(),
                        'updated_at'        => now(),
                    ]);
                    $record = DB::table('wa_connections')->where('id', $connection)->first();
                }

                $qrBase64  = $live['qr_base64'] ?? null;
                $expiresAt = $live['expires_at'] ?? null;
            } catch (BaileysServiceUnavailableException|RequestException) {
                // Fallback: retorna estado do banco sem QR atualizado
            }
        }

        return response()->json([
            'connection_status' => $record->connection_status,
            'phone_number'      => $record->phone_number,
            'qr_base64'         => $qrBase64,
            'expires_at'        => $expiresAt,
            'last_status_at'    => $record->last_status_at,
        ]);
    }

    public function cancelPairing(string $connection, BaileysClient $baileys)
    {
        $record = DB::table('wa_connections')->where('id', $connection)->first();
        abort_unless($record, 404);

        try {
            if ($record->baileys_session_id) {
                $baileys->disconnect($record->baileys_session_id, clearAuth: false);
            }
        } catch (BaileysServiceUnavailableException) {
            // Segue mesmo se Baileys indisponível — atualiza banco
        }

        DB::table('wa_connections')->where('id', $connection)->update([
            'connection_status' => 'disconnected',
            'phone_number'      => null,
            'last_status_at'    => now(),
            'updated_at'        => now(),
        ]);

        return response()->json(['connection_status' => 'disconnected']);
    }

    public function disconnect(string $connection, BaileysClient $baileys)
    {
        $record = DB::table('wa_connections')->where('id', $connection)->first();
        abort_unless($record, 404);

        try {
            if ($record->baileys_session_id) {
                $baileys->disconnect($record->baileys_session_id, clearAuth: true);
            }
        } catch (BaileysServiceUnavailableException) {
            // Continua — desconecta no banco mesmo se serviço offline
        }

        DB::table('wa_connections')->where('id', $connection)->update([
            'connection_status' => 'disconnected',
            'phone_number'      => null,
            'last_status_at'    => now(),
            'updated_at'        => now(),
        ]);

        return redirect()->back()->with('success', 'Conexão desconectada.');
    }

    public function destroy(string $connection, BaileysClient $baileys)
    {
        $record = DB::table('wa_connections')->where('id', $connection)->first();
        abort_unless($record, 404);

        try {
            if ($record->baileys_session_id) {
                $baileys->deleteSession($record->baileys_session_id);
            }
        } catch (BaileysServiceUnavailableException) {
            // Remove do banco mesmo se Baileys offline
        }

        DB::table('wa_connections')->where('id', $connection)->delete();

        return redirect()->back()->with('success', 'Conexão removida.');
    }
}
