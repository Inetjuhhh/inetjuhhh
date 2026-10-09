<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            'Thailand', 'Cambodja', 'Vietnam', 'Ijsland', 'Nederland', 'Peru', 'Indonesië', 'India', 'Nepal'
        ];

        foreach ($countries as $name) {
            Country::firstOrCreate(['name' => $name]);
        }
    }
}
