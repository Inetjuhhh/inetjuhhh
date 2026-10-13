<?php

use App\Support\WordPress\TitlePlace;

$countries = ['Spanje', 'Frankrijk', 'Andorra', 'Noorwegen', 'Peru', 'Portugal', 'Thailand', 'IJsland', 'Vietnam'];
$fixes = ['Snæfelsness' => 'Snæfellsnes'];

it('takes the place out of the title', function () use ($countries) {
    $result = TitlePlace::parse('Rookies op de terugreis [ Lissabon, Dag 4 ]', $countries, [], 'Portugal');

    expect($result['title'])->toBe('Rookies op de terugreis')
        ->and($result['place'])->toBe('Lissabon, Portugal')
        ->and($result['candidates'][0]['queries'][0])->toBe('Lissabon, Portugal');
});

it('takes the first place, and the first country with it', function () use ($countries) {
    expect(TitlePlace::parse('Loodjes [Barcelona , Carcassonne - Spanje, Andorra, Frankrijk]', $countries)['place'])->toBe('Barcelona, Spanje')
        ->and(TitlePlace::parse('Fjorden [Lærdal, Flåm/Myrdal - Noorwegen]', $countries)['place'])->toBe('Lærdal, Noorwegen');
});

it('splits glued places, but keeps a hyphenated name whole', function () use ($countries) {
    expect(TitlePlace::parse('Jungle [Tambopata-Amazone]', $countries, [], 'Peru')['place'])->toBe('Tambopata, Peru')
        ->and(TitlePlace::parse('Pieken [Salkantay-trail]', $countries, [], 'Peru')['place'])->toBe('Salkantay-trail, Peru');
});

it('looks a typo up in its right spelling', function () use ($countries, $fixes) {
    expect(TitlePlace::parse('De natuur [Snæfelsness, IJsland]', $countries, $fixes)['place'])->toBe('Snæfellsnes, IJsland');
});

it('looks up a place without its nickname, but shows it', function () use ($countries) {
    $result = TitlePlace::parse('Stad [Ho Chi Minh City (Saigon)]', $countries, [], 'Vietnam');

    expect($result['place'])->toBe('Ho Chi Minh City (Saigon), Vietnam')
        ->and($result['candidates'][0]['queries'][0])->toBe('Ho Chi Minh City, Vietnam');
});

it('knows a country alone is no specific place', function () use ($countries) {
    $result = TitlePlace::parse('Thai-m Flies [ Thailand ]', $countries);

    expect($result['title'])->toBe('Thai-m Flies')->and($result['place'])->toBe('Thailand')->and($result['candidates'])->toBe([]);
});

it('keeps what is not a place neatly in the title', function () use ($countries) {
    expect(TitlePlace::parse('De reis naar het onbekende [1]', $countries)['title'])->toBe('De reis naar het onbekende (deel 1)')
        ->and(TitlePlace::parse('Omgevlogen [Sam 1 jaar]', $countries)['title'])->toBe('Omgevlogen – Sam 1 jaar');
});

it('makes a title that was only a place into that place', function () use ($countries) {
    expect(TitlePlace::parse('[Kristiansand - Noorwegen]', $countries)['title'])->toBe('Kristiansand, Noorwegen');
});

it('leaves special characters whole', function () use ($countries) {
    expect(TitlePlace::parse('สวัสดีตอนเย็น (goedenavond) [Thailand]', $countries)['title'])->toBe('สวัสดีตอนเย็น (goedenavond)');
});
