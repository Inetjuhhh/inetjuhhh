<?php

/*
| How the old WordPress blog maps onto this one (php artisan wordpress:import).
| Names on the left are as they are in WordPress; matching ignores upper/lower case.
*/

return [

    // WordPress categories (and tags) that are a country, with the country's name here
    'countries' => [
        'Thailand' => 'Thailand',
        'Spanje' => 'Spanje',
        'Vietnam' => 'Vietnam',
        'Noorwegen' => 'Noorwegen',
        'India' => 'India',
        'Peru' => 'Peru',
        'Italië' => 'Italië',
        'Indonesië' => 'Indonesië',
        'Indonesie' => 'Indonesië',
        'Denemarken' => 'Denemarken',
        'IJsland' => 'IJsland',
        'Nepal' => 'Nepal',
        'Portugal' => 'Portugal',
        'Groot-Brittannië' => 'Groot-Brittannië',
        'Zwitserland' => 'Zwitserland',
        'Frankrijk' => 'Frankrijk',
        'Cambodja' => 'Cambodja',
        'Duitsland' => 'Duitsland',
        'Andorra' => 'Andorra',
    ],

    // Other country names that may appear in a title's [brackets] ("[Gerlos, Oostenrijk]")
    'more_countries' => [
        'Oostenrijk', 'Zweden', 'Nederland', 'België', 'Luxemburg', 'Monaco', 'Engeland', 'Schotland', 'Ierland',
        'Griekenland', 'Kroatië', 'Slovenië', 'Tsjechië', 'Hongarije', 'Polen', 'Finland', 'Turkije',
        'Marokko', 'Egypte', 'Laos', 'Myanmar', 'Maleisië', 'Singapore', 'Sri Lanka', 'Japan', 'China',
        'Verenigde Staten', 'Canada', 'Mexico', 'Costa Rica', 'Ecuador', 'Bolivia', 'Chili', 'Argentinië',
        'Brazilië', 'Australië', 'Nieuw-Zeeland', 'Zuid-Afrika', 'Tanzania', 'Kenia',
    ],

    // Places in titles that are spelled differently from the map (typos, nicknames): title spelling → map spelling
    'place_fixes' => [
        'Snæfelsness' => 'Snæfellsnes',
        'Jökurlsárlón' => 'Jökulsárlón',
        'Orachha' => 'Orchha',
        'Salkantay-trail' => 'Salkantay',
    ],

    // Places named in a title without brackets ("Een dagje Bremen …"): word in the title → place to look up.
    // Text between *stars* ("*Taj Mahal*") and the cities below are recognised as well.
    'title_places' => [
        'Bremen' => 'Bremen, Duitsland',
        'Lloret' => 'Lloret de Mar, Spanje',
    ],

    // Fixed places for blogs the rules can't place, by the blog's link (slug, as in WordPress).
    // A place ("Roosendaal, Nederland"), or 'next trip' for a warm-up blog that belongs to the trip after it.
    'place_overrides' => [
        'de-reis-naar-het-onbekende-1' => 'Roosendaal, Nederland',
        'reisonbekende2' => 'Ossendrecht, Nederland',
        'de-reis-naar-het-onbekende-3' => 'Krabbendijke, Nederland',
        'reisbekende4' => 'Dreischor, Nederland',
        '1100-km-verderop-ligt-sneeuw' => 'Le Corbier, Frankrijk',
        'viva-lelafrance-avec-lela-ski' => 'Saint-François-Longchamp, Frankrijk',
        'je-gaat-op-wintersport-en-komt-terug-met-een-netvliesscheur' => "Sauze d'Oulx, Italië",
        'van-egypte-naar-thailand-naar-heeel-veel-er-tussen-naar-uiteindelijk-noorwegen' => 'next trip',
        'sinterkerst-in-warm-gezelschap' => 'Bremgarten, Zwitserland',
    ],

    // Cities: the blog gets the country, and a series of blogs about the city becomes a trip.
    // Also recognised in tags and titles (so "Berlijn" in a title counts too).
    'cities' => [
        'Rome' => 'Italië',
        'Lissabon' => 'Portugal',
        'London' => 'Groot-Brittannië',
        'Londen' => 'Groot-Brittannië',
        'Berlijn' => 'Duitsland',
        'Berlin' => 'Duitsland',
        'Parijs' => 'Frankrijk',
        'Paris' => 'Frankrijk',
        'Dubai' => 'Verenigde Arabische Emiraten',
    ],

    // WordPress category → category here
    'categories' => [
        'Travel' => 'Reizen',
        'Reizen' => 'Reizen',
        'Food' => 'Food',
        'Familie' => 'Lifestyle',
        'Sam' => 'Lifestyle',
        'Lifestyle' => 'Lifestyle',
    ],

    // WordPress categories that become a tag here instead
    'as_tags' => ['Wintersport', 'Performing', 'Musical', 'Klein leed', 'Programmeren', 'Werk'],

    // WordPress categories that are dropped ("Blog" is on almost every post; the rest are post formats)
    'ignore' => ['Blog', 'Afbeelding', 'Video', 'Pics', 'Galerij', 'Uncategorized', 'Geen categorie'],

    // Series that become a trip (besides cities and countries), with the name they get
    'series' => [
        'Wintersport' => 'Wintersport',
    ],

    // Places that were one journey: their blogs go into one trip with this name (plus the year).
    // Add the year ("Thailand 2017") when it only counts for that journey, not every visit.
    'combine' => [
        'India' => 'India & Nepal',
        'Nepal' => 'India & Nepal',
        'Thailand 2017' => 'Thailand & Cambodja',
        'Cambodja 2017' => 'Thailand & Cambodja',
    ],

    // Blogs about the same place belong to one trip when there are at most this many days between them
    'trip_gap_days' => 21,

    // ...and when there are at least this many (a city trip may be a single blog, like Paris)
    'trip_min_posts' => 2,
    'trip_min_posts_city' => 1,

    // File types that are not imported (videos are too large for the server; links to YouTube do come along)
    'skip_media' => ['mov', 'mp4', 'm4v', 'avi', 'zip', 'pdf'],

    // Seconds between two place lookups: OpenStreetMap's free service allows one request per second
    'lookup_pause' => 1.1,

    // Places corrected in the admin, saved with `php artisan wordpress:keep-places`. Part of the
    // project (commit it): the import uses these before any other rule, here and on production.
    'corrections_file' => database_path('wordpress/place-corrections.json'),

    // The folder with the export file and what the import writes next to it: media-map.json (which
    // photo became which) and places.csv (to check the places)
    'directory' => storage_path('app/wordpress'),

    // (Where old pages, categories and links go now is in config/legacy-links.php: that stays after the move)

    // Comments that were still waiting for approval in WordPress: true = they come along as
    // "Wacht op goedkeuring" (approve or delete them in the admin), false = left behind.
    // Spam and the WordPress trash never come along.
    // Off: all 39 waiting comments in the export turned out to be spam (links, casino, Russian adverts).
    'pending_comments' => false,

];
