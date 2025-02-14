<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlogCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blogs = \App\Models\Blog::all();
        $categories = \App\Models\Category::all();
        $subcategories = \App\Models\Subcategory::all();
        // for($i = 0; $i < 2; $i++) {

        // }
        // foreach ($categories as $category) {
        //     $blog = $blogs->random();
        //     $blog->categories()->attach($category);
        // }

        // foreach ($subcategories as $subcategory) {
        //     $blog = $blogs->random();
        //     $blog->subcategories()->attach($subcategory);
        // }

    }
}
