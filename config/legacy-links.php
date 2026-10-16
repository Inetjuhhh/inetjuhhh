<?php

/*
| Where links to the old WordPress blog go now (see LegacyLinkController). Old blog and photo links
| are found in the database (table legacy_links); these are the rest. Keep this file for years,
| also after the import code is removed: old links keep coming in from search results and other sites.
*/

return [

    // The old pages (inetjuhhh.nl/over-ine/ …) → a path on this site
    'pages' => [
        'over-ine' => '/ik-en-wij',
        'travel' => '/kaart',
        'perform' => '/zoeken?q=performing',
        'voetreis' => '/',
        'vork' => '/zoeken?q=food',
        'lootbox' => '/',
        'barts-31e-verjaardag' => '/',
        'fotos' => '/',
        'food' => '/zoeken?q=food',
    ],

    // Old categories (/category/…/name/) that have a place of their own here.
    // Not listed: a country goes to its country page, anything else to the search for that word.
    'categories' => [
        'blog' => '/',
        'travel' => '/kaart',
        'reizen' => '/kaart',
        'familie' => '/categorie/lifestyle',
        'sam' => '/categorie/lifestyle',
        'lifestyle' => '/categorie/lifestyle',
        'food' => '/zoeken?q=Food',
    ],

    // Old city categories → the country whose page they go to
    'cities' => [
        'rome' => 'Italië',
        'lissabon' => 'Portugal',
        'london' => 'Groot-Brittannië',
    ],

];
