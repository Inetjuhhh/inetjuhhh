<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ResponseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Fake('nl_NL');

        $blogs = \App\Models\Blog::all();
        foreach ($blogs as $blog) {
            $response = new \App\Models\Response();
            $response->response = $faker->paragraphs(5, true);
            $response->name = 'Test User';
            $response->email = $faker->email;
            $response->blog_id = $blog->id;
            $response->save();
        }
    }
}
