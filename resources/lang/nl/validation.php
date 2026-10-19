<?php

// Error messages for the checks the site and the admin use (comment form, newsletter, blog form, login).
// A check that isn't listed here falls back to Laravel's English text.
return [
    'accepted' => ':Attribute moet aangevinkt zijn.',
    'after' => ':Attribute moet na :date liggen.',
    'after_or_equal' => ':Attribute mag niet vóór :date liggen.',
    'before' => ':Attribute moet vóór :date liggen.',
    'before_or_equal' => ':Attribute mag niet na :date liggen.',
    'array' => ':Attribute moet een lijst zijn.',
    'boolean' => ':Attribute moet ja of nee zijn.',
    'confirmed' => 'De twee wachtwoorden zijn niet hetzelfde.',
    'current_password' => 'Dat wachtwoord klopt niet.',
    'date' => ':Attribute is geen geldige datum.',
    'email' => 'Vul een geldig e-mailadres in.',
    'exists' => 'De gekozen :attribute bestaat niet.',
    'file' => ':Attribute moet een bestand zijn.',
    'image' => ':Attribute moet een foto zijn.',
    'in' => 'De gekozen :attribute is niet geldig.',
    'integer' => ':Attribute moet een heel getal zijn.',
    'lowercase' => ':Attribute mag alleen kleine letters hebben.',
    'max' => [
        'array' => 'Kies hooguit :max :attribute.',
        'file' => ':Attribute mag hooguit :max kB zijn.',
        'numeric' => ':Attribute mag hooguit :max zijn.',
        'string' => ':Attribute mag hooguit :max tekens lang zijn.',
    ],
    'min' => [
        'array' => 'Kies minstens :min :attribute.',
        'file' => ':Attribute moet minstens :min kB zijn.',
        'numeric' => ':Attribute moet minstens :min zijn.',
        'string' => ':Attribute moet minstens :min tekens lang zijn.',
    ],
    'numeric' => ':Attribute moet een getal zijn.',
    'required' => 'Vul :attribute in.',
    'size' => [
        'string' => ':Attribute moet :size tekens lang zijn.',
    ],
    'string' => ':Attribute moet tekst zijn.',
    'unique' => 'Deze :attribute is al in gebruik.',
    'url' => ':Attribute is geen geldige link.',

    'attributes' => [
        'email' => 'e-mailadres',
        'ends_on' => 'de einddatum',
        'starts_on' => 'de begindatum',
        'name' => 'naam',
        'password' => 'wachtwoord',
        'response' => 'reactie',
    ],
];
