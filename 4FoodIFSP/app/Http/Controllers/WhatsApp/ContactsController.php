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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Exporta todos os contatos como planilha CSV (abre no Excel/Google Sheets).
     * Colunas: nome | numero. O número é exportado em formato re-importável.
     */
    public function export(): StreamedResponse
    {
        $rows = DB::table('wa_contacts')
            ->orderBy('name')
            ->orderBy('phone_digits')
            ->get(['name', 'country_code', 'phone_digits', 'ddd', 'number']);

        $filename = 'contatos-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 para acentos corretos no Excel (Windows).
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nome', 'numero'], ';');

            foreach ($rows as $row) {
                fputcsv($out, [$row->name, $this->formatExportNumber($row)], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Baixa um modelo de planilha (CSV) com a estrutura e exemplos que o cliente
     * deve seguir para importar contatos.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nome', 'numero'], ';');
            fputcsv($out, ['João da Silva', '(14) 99173-6181'], ';');
            fputcsv($out, ['Maria Souza', '14991112222'], ';');

            fclose($out);
        }, 'modelo-importacao-contatos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Importa contatos a partir de uma planilha CSV (colunas nome | numero).
     * Linhas duplicadas (número já cadastrado) são ignoradas; linhas inválidas
     * são contabilizadas e reportadas. Os novos contatos passam a aparecer na
     * lista para atendimento.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Selecione um arquivo para importar.',
            'file.mimes'    => 'Envie um arquivo CSV (use o modelo disponibilizado).',
            'file.max'      => 'O arquivo é muito grande (máximo 5 MB).',
        ]);

        $path   = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'Não foi possível ler o arquivo.']);
        }

        // Detecta o separador (';' usado no modelo; aceita ',' também).
        $firstLine = fgets($handle);
        $firstLine = $firstLine === false ? '' : ltrim($firstLine, "\xEF\xBB\xBF");
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $imported = 0;
        $duplicate = 0;
        $invalid = 0;
        $rowNumber = 0;
        $seen = [];
        $insert = [];

        // Números nacionais já cadastrados (chave de deduplicação).
        $existing = DB::table('wa_contacts')->pluck('phone_digits')->flip();

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            $name = isset($cols[0]) ? trim((string) $cols[0]) : '';
            $raw  = isset($cols[1]) ? (string) $cols[1] : '';

            // Pula linha de cabeçalho e linhas em branco.
            if ($rowNumber === 1 && $this->looksLikeHeader($name, $raw)) {
                continue;
            }
            if ($name === '' && trim($raw) === '') {
                continue;
            }

            $parsed = $this->parseImportNumber($raw);

            if ($name === '' || $name === null || mb_strlen($name) > 120 || $parsed === null) {
                $invalid++;
                continue;
            }

            $national = $parsed['ddd'] . $parsed['number'];

            if (isset($existing[$national]) || isset($seen[$national])) {
                $duplicate++;
                continue;
            }

            $seen[$national] = true;
            $insert[] = [
                'id'           => (string) Str::uuid(),
                'name'         => mb_substr($name, 0, 120),
                'phone_number' => $parsed['country_code'] . $national,
                'country_code' => $parsed['country_code'],
                'phone_digits' => $national,
                'ddd'          => $parsed['ddd'],
                'number'       => $parsed['number'],
                'cpf'          => null,
                'notes'        => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
            $imported++;
        }

        fclose($handle);

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('wa_contacts')->insert($chunk);
        }

        if ($imported === 0 && $duplicate === 0 && $invalid === 0) {
            return back()->withErrors(['file' => 'Nenhum contato encontrado na planilha.']);
        }

        $parts = ["{$imported} adicionado(s)"];
        if ($duplicate > 0) {
            $parts[] = "{$duplicate} já existente(s)";
        }
        if ($invalid > 0) {
            $parts[] = "{$invalid} inválido(s)";
        }

        return back()->with('success', 'Importação concluída: ' . implode(', ', $parts) . '.');
    }

    /**
     * Formata o número para exportação de forma legível e re-importável:
     * "+55 (14) 99173-6181".
     */
    private function formatExportNumber(object $row): string
    {
        $country = (string) ($row->country_code ?? '55');
        $ddd     = (string) ($row->ddd ?? '');
        $number  = (string) ($row->number ?? '');

        if ($ddd === '' || $number === '') {
            return $country . (string) $row->phone_digits;
        }

        $formatted = strlen($number) === 9
            ? substr($number, 0, 5) . '-' . substr($number, 5)
            : (strlen($number) === 8
                ? substr($number, 0, 4) . '-' . substr($number, 4)
                : $number);

        return "+{$country} ({$ddd}) {$formatted}";
    }

    /**
     * Converte o valor da coluna "numero" da planilha em país/DDD/número.
     * Foco em números brasileiros (país 55 por padrão). Retorna null se inválido.
     *
     * @return array{country_code: string, ddd: string, number: string}|null
     */
    private function parseImportNumber(string $raw): ?array
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        // Remove o código do país do Brasil quando presente (55 + DDD + número).
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        // Nacional válido: DDD (2) + número (8 ou 9 dígitos).
        if (! preg_match('/^(\d{2})(\d{8,9})$/', $digits, $m)) {
            return null;
        }

        return [
            'country_code' => '55',
            'ddd'          => $m[1],
            'number'       => $m[2],
        ];
    }

    /**
     * Detecta a linha de cabeçalho do modelo (ex.: "nome;numero").
     */
    private function looksLikeHeader(string $name, string $raw): bool
    {
        $name = mb_strtolower(trim($name));
        $raw  = mb_strtolower(trim($raw));

        return in_array($name, ['nome', 'name'], true)
            && in_array($raw, ['numero', 'número', 'numero ', 'telefone', 'phone'], true);
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
