<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! User::where('email', 'ine@inetjuhhh.nl')->exists()) {
            User::factory()->create([
                'name' => 'inetjuhhh',
                'email' => 'ine@inetjuhhh.nl',
            ]);
        }

        $this->call([
            CategorySeeder::class,
            SubcategorySeeder::class,
            CountrySeeder::class,
            TagSeeder::class,
            BlogSeeder::class, // also seeds the countries, categories, tags and responses per blog
        ]);
    }
}
