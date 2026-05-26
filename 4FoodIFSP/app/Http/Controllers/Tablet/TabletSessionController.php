<?php

namespace App\Http\Controllers\Tablet;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TabletSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mesa' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $mesa = (int) $validated['mesa'];

        $table = DB::table('tables')
            ->where('number', $mesa)
            ->where('active', true)
            ->first();

        if (! $table) {
            return response()->json([
                'message' => 'Mesa não disponível.',
                'errors'  => ['mesa' => ['Mesa não encontrada ou inativa.']],
            ], 422);
        }

        $sessionId = DB::transaction(function () use ($table) {
            $existing = DB::table('table_sessions')
                ->where('table_id', $table->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->id;
            }

            $id = (string) Str::uuid();
            $now = now();

            DB::table('table_sessions')->insert([
                'id'            => $id,
                'table_id'      => $table->id,
                'started_at'    => $now,
                'closed_at'     => null,
                'closed_reason' => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            return $id;
        });

        return response()->json(['session_id' => $sessionId], 201);
    }
}
