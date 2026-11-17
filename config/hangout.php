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

    // Each registrant picks one meal; the name is what gets stored. The
    // frontend's list (src/components/hangout/hangoutMenus.js) adds the
    // "Menu 1" labels, dishes and photos, matched by these names.
    'menus' => [
        'Rice and Chicken',
        'Abula and Beef',
    ],

    /*
    | What Agodi Gardens charges, for the admin's cost estimate only. Never
    | sent to the public page. Each price says where it came from:
    | 'confirmed' marks prices the hangout team has given; the rest are from
    | online guides and posts, which may be out of date (they gave the gate
    | fee as ₦1,000). A game left out (or with a null amount) has no price yet.
    */
    'entry_fee' => [
        'amount' => 1500,
        'confirmed' => true,
        'source' => 'Given by the hangout team, 17 November 2026.',
    ],

    'game_prices' => [
        'Paintball' => [
            'amount' => 3000,
            'confirmed' => true,
            'source' => 'Given by the hangout team, 17 November 2026.',
        ],
        'Archery' => [
            'amount' => 4000,
            'confirmed' => true,
            'source' => 'Given by the hangout team, 17 November 2026.',
        ],
        'Horse Riding' => [
            'amount' => 1000,
            'confirmed' => false,
            'source' => 'A visitor’s post, April 2025: ₦1,000 for a short ride.',
        ],
        'Swimming & Water Slides' => [
            'amount' => 1000,
            'confirmed' => false,
            'source' => 'Tadexprof guide to Agodi Gardens (2026): the pool costs an extra ₦1,000 a person.',
        ],
        'Bowling' => [
            'amount' => 500,
            'confirmed' => false,
            'source' => 'About ₦500, from an online summary of Agodi activities. Not confirmed with the gardens.',
        ],
        'Trampoline & Bouncing Castle' => [
            'amount' => 500,
            'confirmed' => false,
            'source' => 'About ₦500, from an online summary of Agodi activities. Not confirmed with the gardens.',
        ],
    ],

    // Optional financial support goes to this account.
    'support_account' => [
        'account_name' => env('HANGOUT_ACCOUNT_NAME', 'Olajide Israel Okikiola'),
        'bank' => env('HANGOUT_ACCOUNT_BANK', 'Moniepoint'),
        'account_number' => env('HANGOUT_ACCOUNT_NUMBER', '9018140371'),
        'payment_description' => env('HANGOUT_PAYMENT_DESCRIPTION', 'GCCC Hangout 2026'),
    ],

];
