<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestaurantTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        for ($number = 1; $number <= 10; $number++) {
            $existing = DB::table('tables')
                ->where('number', $number)
                ->first();

            if ($existing === null) {
                DB::table('tables')->insert([
                    'id'         => Str::uuid()->toString(),
                    'number'     => $number,
                    'label'      => 'Mesa ' . $number,
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
