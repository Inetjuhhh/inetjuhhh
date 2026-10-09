<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'Backpacken', 'Roadtrip', 'Treinreizen', 'Hiken', 'Strand', 'Natuur', 'Cultuur', 'Stedentrip',
            'Streetfood', 'Recepten', 'Reizen met kinderen', 'Weekendje weg', 'Budget', 'Paklijst',
            'Fotografie', 'Duurzaam reizen', 'Persoonlijk',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}
