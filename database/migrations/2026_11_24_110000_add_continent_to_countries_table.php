<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The continent of a country, for the statistics per continent. Filled in for the countries known by
 * name; any other country gets one when it is edited in the admin (Landen).
 */
return new class extends Migration
{
    private const CONTINENTS = [
        'Europa' => [
            'Albanië', 'Andorra', 'België', 'Bosnië en Herzegovina', 'Bulgarije', 'Cyprus', 'Denemarken', 'Duitsland',
            'Engeland', 'Estland', 'Faeröer', 'Finland', 'Frankrijk', 'Griekenland', 'Groot-Brittannië', 'Hongarije',
            'Ierland', 'IJsland', 'Italië', 'Kroatië', 'Letland', 'Liechtenstein', 'Litouwen', 'Luxemburg', 'Malta',
            'Monaco', 'Montenegro', 'Nederland', 'Noord-Macedonië', 'Noorwegen', 'Oostenrijk', 'Polen', 'Portugal',
            'Roemenië', 'San Marino', 'Schotland', 'Servië', 'Slovenië', 'Slowakije', 'Spanje', 'Tsjechië',
            'Turkije', 'Vaticaanstad', 'Verenigd Koninkrijk', 'Wales', 'Zweden', 'Zwitserland',
        ],
        'Azië' => [
            'Bhutan', 'Cambodja', 'China', 'Filipijnen', 'Georgië', 'India', 'Indonesië', 'Israël', 'Japan', 'Jordanië',
            'Laos', 'Malediven', 'Maleisië', 'Mongolië', 'Myanmar', 'Nepal', 'Oman', 'Qatar', 'Singapore',
            'Sri Lanka', 'Taiwan', 'Thailand', 'Verenigde Arabische Emiraten', 'Vietnam', 'Zuid-Korea',
        ],
        'Afrika' => [
            'Botswana', 'Egypte', 'Ethiopië', 'Ghana', 'Kaapverdië', 'Kenia', 'Madagaskar', 'Marokko', 'Mauritius',
            'Namibië', 'Oeganda', 'Rwanda', 'Senegal', 'Seychellen', 'Tanzania', 'Tunesië', 'Zambia', 'Zimbabwe',
            'Zuid-Afrika',
        ],
        'Noord-Amerika' => [
            'Aruba', 'Bonaire', 'Canada', 'Costa Rica', 'Cuba', 'Curaçao', 'Dominicaanse Republiek', 'Guatemala',
            'Jamaica', 'Mexico', 'Nicaragua', 'Panama', 'Sint Maarten', 'Verenigde Staten',
        ],
        'Zuid-Amerika' => [
            'Argentinië', 'Bolivia', 'Brazilië', 'Chili', 'Colombia', 'Ecuador', 'Paraguay', 'Peru', 'Suriname', 'Uruguay',
        ],
        'Oceanië' => ['Australië', 'Fiji', 'Frans-Polynesië', 'Nieuw-Zeeland'],
    ];

    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('continent', 20)->nullable()->after('name');
        });

        foreach (self::CONTINENTS as $continent => $countries) {
            DB::table('countries')->whereIn('name', $countries)->update(['continent' => $continent]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('continent');
        });
    }
};
