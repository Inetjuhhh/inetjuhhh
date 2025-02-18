<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

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
        $blogs = Blog::all();

        foreach ($countries as $country) {
            \App\Models\Country::create([
                'name' => $country
            ]);
        }

        foreach ($blogs as $blog) {
            $blog->countries()->attach(rand(1, 9));
        }


    }
}
