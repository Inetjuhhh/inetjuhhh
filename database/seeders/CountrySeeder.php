<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Centre of each country, for blogs on the travel map that have no place of their own
        $countries = [
            'Thailand' => [15.8700, 100.9925],
            'India' => [20.5937, 78.9629],
            'Nepal' => [28.3949, 84.1240],
            'Cambodja' => [12.5657, 104.9910],
            'Indonesië' => [-0.7893, 113.9213],
            'Peru' => [-9.1900, -75.0152],
            'Frankrijk' => [46.6034, 1.8883],
            'IJsland' => [64.9631, -19.0208],
            'Denemarken' => [56.2639, 9.5018],
            'Vietnam' => [14.0583, 108.2772],
            'Noorwegen' => [60.4720, 8.4689],
            'Zweden' => [60.1282, 18.6435],
            'Spanje' => [40.4637, -3.7492],
            'Costa Rica' => [9.7489, -83.7534],
            'Nederland' => [52.1326, 5.2913],
        ];

        foreach ($countries as $name => [$latitude, $longitude]) {
            Country::firstOrCreate(['name' => $name], ['latitude' => $latitude, 'longitude' => $longitude]);
        }
    }
}
