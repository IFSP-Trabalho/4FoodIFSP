<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PaymentLinkMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentLinksController extends Controller
{
    private const DEFAULT_MESSAGE = "Olá! Para finalizar seu pedido, faça o pagamento via PIX usando a chave abaixo:\n\n{chave}\n\nApós o pagamento, envie o comprovante por aqui. Obrigado!";

    public function index(): Response
    {
        return Inertia::render('Admin/Cadastros/PaymentLinks', [
            'links'           => $this->getLinks(),
            'defaultMessage'  => self::DEFAULT_MESSAGE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            if ($data['active']) {
                DB::table('payment_links')->update(['active' => false, 'updated_at' => now()]);
            }

            DB::table('payment_links')->insert([
                'id'         => Str::uuid()->toString(),
                'type'       => $data['type'],
                'value'      => $data['value'],
                'label'      => $data['label'],
                'message'    => $data['message'],
                'active'     => $data['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.cadastros.links-pagamento.index')
            ->with('success', 'Link de pagamento cadastrado com sucesso.');
    }

    public function update(Request $request, string $link): RedirectResponse
    {
        $record = DB::table('payment_links')->where('id', $link)->first();
        abort_unless($record, 404);

        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $link) {
            if ($data['active']) {
                DB::table('payment_links')->where('id', '!=', $link)
                    ->update(['active' => false, 'updated_at' => now()]);
            }

            DB::table('payment_links')->where('id', $link)->update([
                'type'       => $data['type'],
                'value'      => $data['value'],
                'label'      => $data['label'],
                'message'    => $data['message'],
                'active'     => $data['active'],
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.cadastros.links-pagamento.index')
            ->with('success', 'Link de pagamento atualizado com sucesso.');
    }

    public function destroy(string $link): RedirectResponse
    {
        $record = DB::table('payment_links')->where('id', $link)->first();
        abort_unless($record, 404);

        DB::table('payment_links')->where('id', $link)->delete();

        return redirect()
            ->route('admin.cadastros.links-pagamento.index')
            ->with('success', 'Link de pagamento excluído com sucesso.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'type'    => ['required', 'in:phone,cpf,cnpj'],
            'value'   => ['required', 'string', 'max:255'],
            'label'   => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4096'],
            'active'  => ['boolean'],
        ], [
            'type.required'    => 'Selecione o tipo da chave.',
            'value.required'   => 'Informe a chave PIX (telefone, CPF ou CNPJ).',
            'message.required' => 'Informe a mensagem que será enviada ao cliente.',
        ]);

        $digits = preg_replace('/\D/', '', $validated['value']);

        if ($validated['type'] === 'cpf' && strlen($digits) !== 11) {
            return $this->failValue('O CPF deve ter 11 dígitos.');
        }
        if ($validated['type'] === 'cnpj' && strlen($digits) !== 14) {
            return $this->failValue('O CNPJ deve ter 14 dígitos.');
        }
        if ($validated['type'] === 'phone' && (strlen($digits) < 10 || strlen($digits) > 13)) {
            return $this->failValue('Informe um telefone válido com DDD.');
        }

        return [
            'type'    => $validated['type'],
            'value'   => $validated['type'] === 'phone' ? $digits : $digits,
            'label'   => isset($validated['label']) && trim($validated['label']) !== '' ? trim($validated['label']) : null,
            'message' => trim($validated['message']),
            'active'  => $request->boolean('active'),
        ];
    }

    private function failValue(string $message): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages(['value' => $message]);
    }

    private function getLinks(): array
    {
        return DB::table('payment_links')
            ->orderByDesc('active')
            ->orderBy('created_at')
            ->get(['id', 'type', 'value', 'label', 'message', 'active'])
            ->map(fn(object $l) => [
                'id'              => (string) $l->id,
                'type'            => (string) $l->type,
                'type_label'      => PaymentLinkMessage::typeLabel($l->type),
                'value'           => (string) $l->value,
                'value_formatted' => PaymentLinkMessage::formatValue($l->type, $l->value),
                'label'           => $l->label,
                'message'         => (string) $l->message,
                'active'          => (bool) $l->active,
                'preview'         => PaymentLinkMessage::compose($l),
            ])
            ->values()
            ->all();
    }
}
