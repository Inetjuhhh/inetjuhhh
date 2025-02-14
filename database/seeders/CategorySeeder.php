<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Fake('nl_NL');
        $category = new \App\Models\Category();
        $category->name = 'Travel';
        $category->save();

        $category = new \App\Models\Category();
        $category->name = 'Food';
        $category->save();

        $category = new \App\Models\Category();
        $category->name = 'Lifestyle';
        $category->save();

        $category = new \App\Models\Category();
        $category->name = 'Family';
        $category->save();

    }
}
