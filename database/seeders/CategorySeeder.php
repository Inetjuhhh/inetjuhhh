<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Reizen', 'Food', 'Lifestyle'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
