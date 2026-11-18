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

    // Each person (a registering adult, or a child they add) picks exactly
    // one, and the name is what gets stored. The frontend shows each game's
    // photos, video and description from its own list
    // (src/components/hangout/hangoutGames.js), matched by these names, so
    // add, rename or remove a game in both places.
    //
    // These are the games and activities Agodi Gardens' management confirmed
    // (November 2026).
    'games' => [
        'Paintball',
        'Archery',
        'Go-Karts & Hoverboards',
        'Horse Riding',
        'Swimming & Water Slides',
        'Football',
        'Basketball',
        'Bowling',
        'Snooker',
        'Trampoline & Bouncing Castle',
        'Bicycle Riding',
        'Kids Power Bike',
        'Table Tennis',
        'Badminton',
        'VR Games',
        'Karaoke',
        'Sip & Paint',
        'Face Painting',
        'Board Games',
    ],

    // Games only children can pick.
    'children_only_games' => [
        'Kids Power Bike',
    ],

    // The most children one adult can add to their registration.
    'max_children' => 10,

    /*
    | Paintball groups. Everyone who picks the game (adults and children) is
    | put into groups that play against each other: as many groups as there
    | can be with at least `min_group_size` each, so groups stay small and
    | orderly, with women and men (and children) spread evenly. Groups are
    | worked out again as people register, and are final once registration
    | closes. Groups take these names in order (a second round adds "II").
    */
    'paintball' => [
        'game' => 'Paintball',
        'min_group_size' => 5,
        'group_names' => [
            ['name' => "Gideon's 300", 'reference' => 'Judges 7:7', 'motto' => 'A small band with a big God.'],
            ['name' => 'Jericho Wall Breakers', 'reference' => 'Joshua 6:20', 'motto' => 'Seven laps, one shout, no walls left.'],
            ['name' => "David's Slingers", 'reference' => '1 Samuel 17:49', 'motto' => 'It only took one good shot.'],
            ['name' => "Joseph's Coat of Many Colours", 'reference' => 'Genesis 37:3', 'motto' => 'You’ll leave in many colours too.'],
            ['name' => "Lions' Den Survivors", 'reference' => 'Daniel 6:22', 'motto' => 'Not a scratch on them.'],
            ['name' => 'Fiery Furnace Crew', 'reference' => 'Daniel 3:27', 'motto' => 'Out without even the smell of smoke.'],
            ['name' => 'Shield of Faith', 'reference' => 'Ephesians 6:16', 'motto' => 'Ready for every flaming dart.'],
            ['name' => 'Red Sea Crossers', 'reference' => 'Exodus 14:22', 'motto' => 'Dry ground all the way through.'],
            ['name' => "Nehemiah's Wall Builders", 'reference' => 'Nehemiah 4:17', 'motto' => 'One hand on the work, one on the defence.'],
            ['name' => 'Chariots of Fire', 'reference' => '2 Kings 2:11', 'motto' => 'Fast, fiery and always heading up.'],
            ['name' => "Deborah's Riders", 'reference' => 'Judges 5:12', 'motto' => 'Wake up, wake up, and lead the way.'],
            ['name' => 'The Mighty Warriors', 'reference' => 'Judges 6:12', 'motto' => 'The Lord is with you, mighty warriors.'],
            ['name' => "Noah's Ark Rangers", 'reference' => 'Genesis 7:9', 'motto' => 'In two by two, out victorious.'],
            ['name' => "Esther's Royal Guard", 'reference' => 'Esther 4:14', 'motto' => 'Made for such a time as this.'],
            ['name' => 'The Water Walkers', 'reference' => 'Matthew 14:29', 'motto' => 'Eyes up, keep walking.'],
            ['name' => "Joshua's Trumpeters", 'reference' => 'Joshua 6:16', 'motto' => 'Shout, for the Lord has given you the city!'],
            ['name' => "Elisha's Double Portion", 'reference' => '2 Kings 2:9', 'motto' => 'Twice the spirit, twice the aim.'],
            ['name' => 'Burning Bush Brigade', 'reference' => 'Exodus 3:2', 'motto' => 'On fire, never burnt out.'],
            ['name' => 'Mustard Seed Movers', 'reference' => 'Matthew 17:20', 'motto' => 'Small seed, mountain-moving faith.'],
            ['name' => 'Salt and Light', 'reference' => 'Matthew 5:13–14', 'motto' => 'Bringing the flavour, lighting up the field.'],
        ],
    ],

    // Each person picks one meal; the name is what gets stored. The
    // frontend's list (src/components/hangout/hangoutMenus.js) adds the
    // "Menu 1" labels, dishes and photos, matched by these names.
    'menus' => [
        'Rice and Chicken',
        'Abula and Beef',
    ],

    // Everyone gives one; the frontend offers the same two.
    'genders' => [
        'Male',
        'Female',
    ],

    /*
    | What each person costs, for the admin's cost estimate only. Never sent to
    | the public page. 'adult' and 'child' are each age's price; 'amount' is one
    | price for both. A missing or null price means none is set yet (or, for
    | 'adult' on a children-only game, that adults can't play it); 0 means
    | free. 'confirmed' marks prices from Agodi Gardens' management or the
    | hangout team, and each says where it came from. Where a game has several
    | packages, the estimate uses the standard one and the source lists the rest.
    */
    'entry_fee' => [
        'amount' => 1500,
        'confirmed' => true,
        'source' => 'Gate fee given by the hangout team, 17 November 2026. No separate children’s fee was given, so children are counted at the same fee.',
    ],

    'game_prices' => [
        'Paintball' => [
            'amount' => 3500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦3,500 for 20 paintballs.',
        ],
        'Archery' => [
            'amount' => 2000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: 6 shots ₦2,000, 12 shots ₦3,500, 30 minutes ₦7,000. The estimate uses 6 shots.',
        ],
        'Go-Karts & Hoverboards' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: hoverboard and go-kart ride ₦1,000; off-road hoverboard ride ₦1,500.',
        ],
        'Horse Riding' => [
            'adult' => 1000,
            'child' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: adults ₦1,000 (₦2,000 on special request), children ₦500 (₦1,000 on special request).',
        ],
        'Swimming & Water Slides' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: pool access ₦1,000.',
        ],
        'Football' => [
            'amount' => 0,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: free.',
        ],
        'Basketball' => [
            'amount' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: bin basket ₦500; mobile basketball net ₦500.',
        ],
        'Bowling' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦1,000.',
        ],
        'Snooker' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦1,000.',
        ],
        'Trampoline & Bouncing Castle' => [
            'adult' => 1000,
            'child' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: trampoline adults ₦1,000, children ₦500; bouncing castle ₦500.',
        ],
        'Bicycle Riding' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦1,000.',
        ],
        'Kids Power Bike' => [
            'child' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: children’s power bike ₦1,000.',
        ],
        'Table Tennis' => [
            'amount' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦500.',
        ],
        'Badminton' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦1,000.',
        ],
        'VR Games' => [
            'adult' => 2500,
            'child' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: adult VR experience ₦2,500, children’s VR experience ₦1,000; sport and RPG VR ₦2,500 to ₦5,000.',
        ],
        'Karaoke' => [
            'amount' => 1000,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: karaoke alone ₦1,000.',
        ],
        'Sip & Paint' => [
            'amount' => 8500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: sip and paint ₦8,500, karaoke included.',
        ],
        'Face Painting' => [
            'amount' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management: ₦500.',
        ],
        'Board Games' => [
            'adult' => 1000,
            'child' => 500,
            'confirmed' => true,
            'source' => 'Agodi Gardens management lists board games at ₦1,000 and at ₦500; the estimate takes ₦1,000 for adults and ₦500 for children.',
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
