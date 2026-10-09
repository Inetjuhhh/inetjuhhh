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
        foreach (['Travel', 'Food', 'Lifestyle', 'Family'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
