<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Support\WaPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactsController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $name   = trim((string) $request->query('name', ''));
        $number = trim((string) $request->query('number', ''));
        $page   = max(1, (int) $request->query('page', 1));

        $query = DB::table('wa_contacts');

        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($number !== '') {
            $numberDigits = preg_replace('/\D/', '', $number);
            if ($numberDigits !== '') {
                $query->where('phone_digits', 'like', '%' . $numberDigits . '%');
            }
        }

        $total    = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min($page, $lastPage);

        $contacts = $query
            ->orderBy('name')
            ->orderBy('phone_digits')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get(['id', 'name', 'phone_number', 'country_code', 'phone_digits', 'ddd', 'number', 'cpf', 'notes'])
            ->map(fn (object $c) => [
                'id'           => (string) $c->id,
                'name'         => $c->name,
                'country_code' => (string) ($c->country_code ?? '55'),
                'phone_digits' => (string) $c->phone_digits,
                'ddd'          => $c->ddd,
                'number'       => $c->number,
                'cpf'          => $c->cpf,
                'notes'        => $c->notes,
            ])
            ->all();

        return Inertia::render('WhatsApp/Contatos', [
            'contacts'   => $contacts,
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'total'        => $total,
            ],
            'filters' => [
                'name'   => $name,
                'number' => $number,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateContact($request);

        $national = $validated['ddd'] . $validated['number'];

        if (DB::table('wa_contacts')->where('phone_digits', $national)->exists()) {
            return back()->withErrors(['number' => 'Já existe um contato com este número.']);
        }

        DB::table('wa_contacts')->insert([
            'id'           => (string) Str::uuid(),
            'name'         => $validated['name'],
            'phone_number' => $validated['country_code'] . $national,
            'country_code' => $validated['country_code'],
            'phone_digits' => $national,
            'ddd'          => $validated['ddd'],
            'number'       => $validated['number'],
            'cpf'          => $validated['cpf'],
            'notes'        => $validated['notes'],
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return back()->with('success', 'Contato cadastrado com sucesso.');
    }

    public function update(Request $request, string $contact): RedirectResponse
    {
        $record = DB::table('wa_contacts')->where('id', $contact)->first();
        abort_unless($record, 404);

        $validated = $this->validateContact($request);

        $national = $validated['ddd'] . $validated['number'];

        $duplicate = DB::table('wa_contacts')
            ->where('phone_digits', $national)
            ->where('id', '!=', $contact)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['number' => 'Já existe um contato com este número.']);
        }

        // Mantém o identificador WhatsApp original quando número e país não mudam
        // (preserva o vínculo com tickets); regenera quando algum deles muda.
        $unchanged = $national === (string) $record->phone_digits
            && $validated['country_code'] === (string) ($record->country_code ?? '55');

        $phoneNumber = $unchanged
            ? $record->phone_number
            : $validated['country_code'] . $national;

        DB::table('wa_contacts')->where('id', $contact)->update([
            'name'         => $validated['name'],
            'phone_number' => $phoneNumber,
            'country_code' => $validated['country_code'],
            'phone_digits' => $national,
            'ddd'          => $validated['ddd'],
            'number'       => $validated['number'],
            'cpf'          => $validated['cpf'],
            'notes'        => $validated['notes'],
            'updated_at'   => now(),
        ]);

        // Propaga o nome para os tickets vinculados, refletindo na tela de Atendimento.
        $this->syncTicketNames($national, $validated['name']);

        return back()->with('success', 'Contato atualizado com sucesso.');
    }

    public function destroy(string $contact): RedirectResponse
    {
        $record = DB::table('wa_contacts')->where('id', $contact)->first();
        abort_unless($record, 404);

        if ($this->openTicketFor($record)) {
            return back()->withErrors([
                'delete' => 'Não é possível remover este contato enquanto houver atendimento ativo.',
            ]);
        }

        DB::table('wa_contacts')->where('id', $contact)->delete();

        return back()->with('success', 'Contato removido com sucesso.');
    }

    /**
     * Abrir Atendimento: redireciona para o ticket aberto mais recente vinculado
     * ao contato; se não houver, cria um novo atendimento em andamento.
     */
    public function openTicket(string $contact): RedirectResponse
    {
        $record = DB::table('wa_contacts')->where('id', $contact)->first();
        abort_unless($record, 404);

        $connection = $this->connectedConnection();

        $open = $this->openTicketFor($record);

        if ($open) {
            // Garante o vínculo de conexão em tickets abertos que ainda não o têm.
            if (! $open->wa_connection_id && $connection) {
                DB::table('wa_tickets')->where('id', $open->id)->update([
                    'wa_connection_id' => $connection->id,
                ]);
            }

            return redirect()->route('whatsapp.inbox', ['ticket' => $open->id]);
        }

        $ticketId = (string) Str::uuid();

        DB::table('wa_tickets')->insert([
            'id'               => $ticketId,
            'wa_connection_id' => $connection?->id,
            'phone_number'     => $record->phone_number,
            'customer_name'    => $record->name,
            'status'           => 'in_progress',
            'agent_id'         => auth()->id(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return redirect()->route('whatsapp.inbox', ['ticket' => $ticketId]);
    }

    /**
     * Localiza o ticket aberto (triagem ou em andamento) mais recente cujo número
     * nacional coincide com o do contato.
     */
    private function openTicketFor(object $contact): ?object
    {
        $national = (string) $contact->phone_digits;

        return DB::table('wa_tickets')
            ->whereIn('status', ['triage', 'in_progress'])
            ->orderByDesc('updated_at')
            ->get(['id', 'phone_number', 'status', 'updated_at', 'wa_connection_id'])
            ->first(fn (object $t) => WaPhone::national($t->phone_number) === $national);
    }

    /**
     * Atualiza o customer_name dos tickets cujo número nacional coincide com o
     * do contato, para que a edição do nome reflita na tela de Atendimento.
     */
    private function syncTicketNames(string $national, ?string $name): void
    {
        $ids = DB::table('wa_tickets')
            ->get(['id', 'phone_number'])
            ->filter(fn (object $t) => WaPhone::national($t->phone_number) === $national)
            ->pluck('id')
            ->all();

        if ($ids) {
            DB::table('wa_tickets')
                ->whereIn('id', $ids)
                ->update(['customer_name' => $name]);
        }
    }

    /**
     * Conexão WhatsApp conectada e utilizável mais recente (com sessão Baileys).
     */
    private function connectedConnection(): ?object
    {
        return DB::table('wa_connections')
            ->where('connection_status', 'connected')
            ->whereNotNull('baileys_session_id')
            ->orderByDesc('last_status_at')
            ->first();
    }

    private function validateContact(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'ddd'          => ['required', 'string', 'regex:/^\d{2,3}$/'],
            'number'       => ['required', 'string', 'regex:/^\d{8,9}$/'],
            'cpf'          => ['nullable', 'string', 'max:20'],
            'notes'        => ['nullable', 'string', 'max:4096'],
        ], [
            'name.required'         => 'Informe o nome do contato.',
            'country_code.required' => 'Selecione o país.',
            'country_code.regex'    => 'Código de país inválido.',
            'ddd.required'          => 'Informe o DDD.',
            'ddd.regex'             => 'DDD inválido.',
            'number.required'       => 'Informe o número.',
            'number.regex'          => 'Número inválido (8 ou 9 dígitos).',
        ]);

        return [
            'name'         => trim($data['name']),
            'country_code' => $data['country_code'],
            'ddd'          => $data['ddd'],
            'number'       => $data['number'],
            'cpf'          => isset($data['cpf']) && trim($data['cpf']) !== '' ? trim($data['cpf']) : null,
            'notes'        => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
        ];
    }
}
