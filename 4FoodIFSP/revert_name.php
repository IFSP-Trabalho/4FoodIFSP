<?php
use Illuminate\Support\Facades\DB;
DB::table("wa_contacts")->update(["name" => "1223"]);
echo "revertido\n";
