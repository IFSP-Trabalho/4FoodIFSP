<?php

use App\Http\Controllers\WhatsApp\ContactsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$contact = DB::table('wa_contacts')->first();
echo "ANTES: name={$contact->name}\n";

$req = Request::create("/whatsapp/contatos/{$contact->id}", 'PUT', [
    'name'         => 'NOME_TESTE_EDIT',
    'country_code' => '55',
    'ddd'          => $contact->ddd,
    'number'       => $contact->number,
    'cpf'          => null,
    'notes'        => null,
]);

try {
    $controller = new ContactsController();
    $controller->update($req, $contact->id);
    $after = DB::table('wa_contacts')->where('id', $contact->id)->first();
    echo "DEPOIS: name={$after->name}\n";
} catch (\Throwable $e) {
    echo "ERRO: " . get_class($e) . " - " . $e->getMessage() . "\n";
}
