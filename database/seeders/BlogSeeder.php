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
        $blog->excerpt = $faker->sentences(3, true);
        $blog->content = $faker->paragraphs(20, true);
        $blog->published_at = $faker->dateTimeBetween('-1 year', 'now');
        $blog->placed_by_id = 1;
        $blog->save();

        $blog = new \App\Models\Blog();
        $blog->title = 'Hoe maak ik een blog in PHP?';
        $blog->slug = 'hoe-maak-ik-een-blog-in-php';
        $blog->excerpt = $faker->sentences(3, true);
        $blog->content = $faker->paragraphs(20, true);
        $blog->published_at = $faker->dateTimeBetween('-1 year', 'now');
        $blog->placed_by_id = 1;
        $blog->save();

        for($i = 0; $i < 20; $i++) {
            $blog = new \App\Models\Blog();
            $blog->title = $faker->sentence(6, true);
            $blog->slug = $faker->slug();
            $blog->excerpt = $faker->sentences(3, true);
            $blog->content = $faker->paragraphs(20, true);
            $blog->published_at = $faker->dateTimeBetween('-1 year', 'now');
            $blog->placed_by_id = 1;
            $blog->save();
        }


    }
}
