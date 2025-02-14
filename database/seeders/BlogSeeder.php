<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Fake('nl_NL');
        $blog = new \App\Models\Blog();
        $blog->title = 'Hoe maak ik een blog in Laravel?';
        $blog->slug = 'hoe-maak-ik-een-blog-in-laravel';
        $blog->content = $faker->paragraphs(5, true);
        $blog->placed_by = 1;
        $blog->save();

        $blog = new \App\Models\Blog();
        $blog->title = 'Hoe maak ik een blog in PHP?';
        $blog->slug = 'hoe-maak-ik-een-blog-in-php';
        $blog->content = $faker->paragraphs(5, true);
        $blog->placed_by = 1;
        $blog->save();


    }
}
