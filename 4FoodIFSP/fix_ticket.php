<?php

use App\Support\WaPhone;
use Illuminate\Support\Facades\DB;

$contact = DB::table('wa_contacts')->first();
$national = (string) $contact->phone_digits;

$ids = DB::table('wa_tickets')->get(['id', 'phone_number'])
    ->filter(fn ($t) => WaPhone::national($t->phone_number) === $national)
    ->pluck('id')->all();

if ($ids) {
    DB::table('wa_tickets')->whereIn('id', $ids)->update(['customer_name' => $contact->name]);
}

echo "Tickets sincronizados com nome do contato: {$contact->name}\n";
foreach (DB::table('wa_tickets')->whereIn('id', $ids)->get(['phone_number', 'customer_name']) as $t) {
    echo " - {$t->phone_number} => {$t->customer_name}\n";
}
