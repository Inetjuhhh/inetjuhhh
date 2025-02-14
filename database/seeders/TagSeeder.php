<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for($i = 0; $i < 10; $i++) {
            $faker = Fake('nl_NL');
            $tag = new \App\Models\Tag();
            $tag->name = $faker->word;
            $tag->save();
        }
    }
}
