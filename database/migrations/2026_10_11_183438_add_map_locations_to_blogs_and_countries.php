<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Centre of the countries that already exist, so they show on the travel map without
     * a lookup. New countries get their coordinates from OpenStreetMap when they are saved.
     */
    private const COUNTRY_CENTRES = [
        'thailand' => [15.8700, 100.9925],
        'cambodja' => [12.5657, 104.9910],
        'vietnam' => [14.0583, 108.2772],
        'ijsland' => [64.9631, -19.0208],
        'nederland' => [52.1326, 5.2913],
        'peru' => [-9.1900, -75.0152],
        'indonesië' => [-0.7893, 113.9213],
        'india' => [20.5937, 78.9629],
        'nepal' => [28.3949, 84.1240],
    ];

    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('location_name')->nullable()->after('excerpt');
            $table->decimal('latitude', 10, 7)->nullable()->after('location_name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        foreach (DB::table('countries')->get() as $country) {
            if ($centre = self::COUNTRY_CENTRES[mb_strtolower(trim($country->name))] ?? null) {
                DB::table('countries')->where('id', $country->id)
                    ->update(['latitude' => $centre[0], 'longitude' => $centre[1]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn(['location_name', 'latitude', 'longitude']);
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
