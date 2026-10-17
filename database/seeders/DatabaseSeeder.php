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
        // The admin account for a fresh local install; its password is "password" (UserFactory)
        if (! User::where('email', 'inetjuhhh@gmail.com')->exists()) {
            User::factory()->create([
                'name' => 'Ine',
                'email' => 'inetjuhhh@gmail.com',
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
