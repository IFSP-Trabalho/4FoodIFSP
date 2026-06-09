<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Chatbot/Index', [
            'bots' => $this->getBots(),
        ]);
    }

    private function getBots(): array
    {
        return DB::table('chatbot_flows')
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (object $flow): array => [
                'id'     => (string) $flow->id,
                'name'   => (string) $flow->name,
                'active' => (bool) $flow->active,
            ])
            ->values()
            ->all();
    }
}
