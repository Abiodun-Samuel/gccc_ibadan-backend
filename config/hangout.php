<?php

/*
|--------------------------------------------------------------------------
| GCCC Hangout
|--------------------------------------------------------------------------
|
| The church hangout is a stand-alone feature, separate from events. The page
| itself lives in the frontend; these are the settings the API enforces and
| serves: when it happens, when registration closes, the games people can
| pick from, and the account that support (donations) goes to.
|
*/

return [

    'title' => 'GCCC Hangout 2026',

    // In the app timezone (Africa/Lagos).
    'starts_at' => env('HANGOUT_STARTS_AT', '2026-11-28 10:00:00'),

    // Registration closes at this moment. Leave empty to close when the hangout starts.
    'registration_closes_at' => env('HANGOUT_REGISTRATION_CLOSES_AT'),

    // Each registrant picks exactly one, and the name is what gets stored. The
    // frontend shows each game's photos, video and description from its own
    // list (src/components/hangout/hangoutGames.js), matched by these names, so
    // add, rename or remove a game in both places.
    'games' => [
        'Paintball',
        'Archery',
        'Horse Riding',
        'Swimming & Water Slides',
        'Bowling',
        'Trampoline & Bouncing Castle',
        'Bicycle Riding',
        'Table Tennis & Badminton',
        'VR Games',
        'Board Games',
    ],

    // Optional financial support goes to this account.
    'support_account' => [
        'account_name' => env('HANGOUT_ACCOUNT_NAME', 'Olajide Israel Okikiola'),
        'bank' => env('HANGOUT_ACCOUNT_BANK', 'Moniepoint'),
        'account_number' => env('HANGOUT_ACCOUNT_NUMBER', '9018140371'),
        'payment_description' => env('HANGOUT_PAYMENT_DESCRIPTION', 'GCCC Hangout 2026'),
    ],

];
