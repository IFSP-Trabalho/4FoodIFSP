<?php

use App\Http\Controllers\WhatsApp\ContactsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$contact = DB::table('wa_contacts')->first();

echo "Ticket ANTES: " . DB::table('wa_tickets')->where('phone_number', '5514991736181')->value('customer_name') . "\n";

$req = Request::create("/whatsapp/contatos/{$contact->id}", 'PUT', [
    'name'         => 'Cliente Editado',
    'country_code' => $contact->country_code,
    'ddd'          => $contact->ddd,
    'number'       => $contact->number,
    'cpf'          => $contact->cpf,
    'notes'        => $contact->notes,
]);

(new ContactsController())->update($req, $contact->id);

echo "Contato DEPOIS: " . DB::table('wa_contacts')->where('id', $contact->id)->value('name') . "\n";
echo "Ticket DEPOIS: " . DB::table('wa_tickets')->where('phone_number', '5514991736181')->value('customer_name') . "\n";

// reverte o contato para o estado original
DB::table('wa_contacts')->where('id', $contact->id)->update(['name' => $contact->name]);
echo "Contato revertido para: {$contact->name}\n";
