<?php

namespace App\Services\WhatsApp;

use App\Exceptions\BaileysServiceUnavailableException;
use Illuminate\Support\Facades\Http;

class BaileysClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.baileys.url', 'http://127.0.0.1:3001'), '/');
    }

    public function createSession(string $connectionId): array
    {
        return $this->send(fn() =>
            Http::timeout(5)->post("{$this->baseUrl}/sessions", [
                'connection_id' => $connectionId,
            ])
        );
    }

    public function startSession(string $sessionId): array
    {
        return $this->send(fn() =>
            Http::timeout(15)->post("{$this->baseUrl}/sessions/{$sessionId}/start")
        );
    }

    public function getStatus(string $sessionId): array
    {
        return $this->send(fn() =>
            Http::timeout(5)->get("{$this->baseUrl}/sessions/{$sessionId}/status")
        );
    }

    public function disconnect(string $sessionId, bool $clearAuth = false): array
    {
        return $this->send(fn() =>
            Http::timeout(5)->post("{$this->baseUrl}/sessions/{$sessionId}/disconnect", [
                'clear_auth' => $clearAuth,
            ])
        );
    }

    public function deleteSession(string $sessionId): array
    {
        return $this->send(fn() =>
            Http::timeout(5)->delete("{$this->baseUrl}/sessions/{$sessionId}")
        );
    }

    private function send(callable $call): array
    {
        try {
            $response = $call();
            $response->throw();
            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new BaileysServiceUnavailableException();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() >= 500) {
                throw new BaileysServiceUnavailableException();
            }
            throw $e;
        }
    }
}
