<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WaClosureReasonsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cadastros/WaMotivos', [
            'reasons' => $this->getReasons(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:wa_closure_reasons,name'],
        ], [
            'name.required' => 'Informe o nome do motivo.',
            'name.unique'   => 'Já existe um motivo com este nome.',
        ]);

        DB::table('wa_closure_reasons')->insert([
            'id'         => Str::uuid()->toString(),
            'name'       => trim($request->name),
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.cadastros.wa-motivos.index')
            ->with('success', 'Motivo cadastrado com sucesso.');
    }

    public function update(Request $request, string $reason): RedirectResponse
    {
        $record = DB::table('wa_closure_reasons')->where('id', $reason)->first();
        abort_unless($record, 404);

        $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
        ], [
            'name.required' => 'Informe o nome do motivo.',
        ]);

        DB::table('wa_closure_reasons')->where('id', $reason)->update([
            'name'       => trim($request->name),
            'active'     => $request->boolean('active'),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.cadastros.wa-motivos.index')
            ->with('success', 'Motivo atualizado com sucesso.');
    }

    public function destroy(string $reason): RedirectResponse
    {
        $record = DB::table('wa_closure_reasons')->where('id', $reason)->first();
        abort_unless($record, 404);

        $inUse = DB::table('wa_tickets')
            ->where('closure_reason_id', $reason)
            ->exists();

        if ($inUse) {
            return redirect()
                ->route('admin.cadastros.wa-motivos.index')
                ->withErrors(['delete' => 'Este motivo não pode ser excluído pois está vinculado a atendimentos.']);
        }

        DB::table('wa_closure_reasons')->where('id', $reason)->delete();

        return redirect()
            ->route('admin.cadastros.wa-motivos.index')
            ->with('success', 'Motivo excluído com sucesso.');
    }

    private function getReasons(): array
    {
        return DB::table('wa_closure_reasons')
            ->orderBy('name')
            ->get(['id', 'name', 'active', 'created_at'])
            ->map(fn(object $r) => [
                'id'     => (string) $r->id,
                'name'   => (string) $r->name,
                'active' => (bool) $r->active,
            ])
            ->values()
            ->all();
    }
}
