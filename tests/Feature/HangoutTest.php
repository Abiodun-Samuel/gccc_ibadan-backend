<?php

use App\Enums\RoleEnum;
use App\Models\HangoutRegistration;
use App\Models\HangoutRegistrationChild;
use App\Models\User;
use App\Services\HangoutRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Registration is open until the hangout starts (2026-11-28 10:00); pin "now" before it.
beforeEach(fn() => $this->travelTo('2026-11-01 12:00:00'));

function hangoutPayload(array $overrides = []): array
{
    return array_merge([
        'full_name'       => 'Ada Obi',
        'gender'          => 'Female',
        'email'           => 'ada@example.com',
        'phone_number'    => '08012345678',
        'whatsapp_number' => '08087654321',
        'game'            => 'Archery',
        'menu'            => 'Rice and Chicken',
        'support_amount'  => 5000,
    ], $overrides);
}

function hangoutUserToken(?string $role = null): string
{
    $user = User::forceCreate([
        'first_name'   => 'Test',
        'last_name'    => 'User',
        'email'        => uniqid() . '@example.com',
        'phone_number' => '08000000000',
        'password'     => Hash::make('password'),
    ]);
    if ($role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user->createToken('test')->plainTextToken;
}

function seedHangoutRegistrations(): void
{
    $rows = [
        ['Chidi Okafor', 'Male', 'chidi@example.com', '08011111111', null, 'Paintball', 'Abula and Beef', null, '2026-10-01'],
        ['Bola Ade', 'Female', 'bola@example.com', '08022222222', '08022222222', 'Archery', 'Rice and Chicken', 2000, '2026-10-02'],
        ['Ada Obi', 'Female', 'ada@example.com', '08033333333', null, 'Archery', 'Rice and Chicken', 10000, '2026-10-03'],
        // Registered before meals and gender were asked.
        ['Dayo Lawal', null, 'dayo@example.com', '08044444444', null, 'Board Games', null, 500, '2026-10-04'],
    ];
    foreach ($rows as [$name, $gender, $email, $phone, $wa, $game, $menu, $amount, $date]) {
        $registration = HangoutRegistration::create([
            'full_name' => $name, 'gender' => $gender, 'email' => $email, 'phone_number' => $phone,
            'whatsapp_number' => $wa, 'game' => $game, 'menu' => $menu, 'support_amount' => $amount,
        ]);
        $registration->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
    }

    // Ada brings two children.
    HangoutRegistration::where('email', 'ada@example.com')->sole()->children()->createMany([
        ['full_name' => 'Tobi Obi', 'gender' => 'Male', 'game' => 'Horse Riding', 'menu' => 'Rice and Chicken'],
        ['full_name' => 'Kemi Obi', 'gender' => 'Female', 'game' => 'Kids Power Bike', 'menu' => 'Abula and Beef'],
    ]);
}

/** Known prices, so the estimate tests don't depend on what the config says today. */
function useHangoutPrices(): void
{
    config([
        'hangout.entry_fee' => ['adult' => 1500, 'child' => 1000, 'confirmed' => true, 'source' => 'Hangout team.'],
        'hangout.game_prices' => [
            'Paintball'       => ['amount' => 3000, 'confirmed' => true, 'source' => 'Hangout team.'],
            'Archery'         => ['adult' => 4000, 'child' => 2000, 'confirmed' => true, 'source' => 'Hangout team.'],
            'Horse Riding'    => ['adult' => 1000, 'child' => 500, 'confirmed' => true, 'source' => 'Agodi Gardens.'],
            'Kids Power Bike' => ['child' => 1000, 'confirmed' => true, 'source' => 'Agodi Gardens.'],
            'Bowling'         => ['amount' => 500, 'confirmed' => false, 'source' => 'An online summary.'],
        ],
    ]);
}

/** A child as the registration form sends one. */
function hangoutChild(array $overrides = []): array
{
    return array_merge(['full_name' => 'Tobi Obi', 'gender' => 'Male', 'game' => 'Kids Power Bike', 'menu' => 'Rice and Chicken'], $overrides);
}

/** The files inside an .xlsx (a stored zip), checking each one's CRC. */
function readXlsxParts(string $bytes): array
{
    $parts = [];
    $at = 0;
    while (substr($bytes, $at, 4) === "PK\x03\x04") {
        $h = unpack('Vsig/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vnameLength/vextraLength', substr($bytes, $at, 30));
        $name = substr($bytes, $at + 30, $h['nameLength']);
        $data = substr($bytes, $at + 30 + $h['nameLength'] + $h['extraLength'], $h['compressed']);
        expect($h['method'])->toBe(0)->and(crc32($data))->toBe($h['crc']);
        $parts[$name] = $data;
        $at += 30 + $h['nameLength'] + $h['extraLength'] + $h['compressed'];
    }

    return $parts;
}

describe('public hangout details', function () {
    it('serves the games, meals, support account and whether registration is open, with no prices', function () {
        HangoutRegistration::create(hangoutPayload());

        $response = $this->getJson('/api/hangout')
            ->assertOk()
            ->assertJsonPath('data.title', 'GCCC Hangout 2026')
            ->assertJsonPath('data.registration_open', true)
            ->assertJsonPath('data.registrations_count', 1)
            ->assertJsonCount(count(config('hangout.games')), 'data.games')
            ->assertJsonPath('data.games.1', ['name' => 'Archery', 'registrations_count' => 1, 'children_only' => false])
            ->assertJsonPath('data.menus', [['name' => 'Rice and Chicken'], ['name' => 'Abula and Beef']])
            ->assertJsonPath('data.max_children', 10)
            ->assertJsonPath('data.support_account.account_number', '9018140371')
            ->assertJsonMissingPath('data.estimate')
            ->assertJsonMissingPath('data.entry_fee');

        expect($response->getContent())->not->toContain('price')->not->toContain('source');
    });

    it('counts only games that are still on the list', function () {
        HangoutRegistration::create(hangoutPayload(['game' => 'Golf']));

        $games = collect($this->getJson('/api/hangout')->json('data.games'));

        expect($games)->toHaveCount(count(config('hangout.games')))
            ->and($games->pluck('name'))->not->toContain('Golf')
            ->and($games->sum('registrations_count'))->toBe(0);
    });

    it('counts children in how many are going, and marks the games only children can pick', function () {
        seedHangoutRegistrations();

        $games = collect($this->getJson('/api/hangout')->json('data.games'))->keyBy('name');

        expect($games['Horse Riding'])->toMatchArray(['registrations_count' => 1, 'children_only' => false])
            ->and($games['Kids Power Bike'])->toMatchArray(['registrations_count' => 1, 'children_only' => true])
            ->and($games->where('children_only', true)->keys()->all())->toBe(['Kids Power Bike']);
    });
});

describe('public registration', function () {
    it('registers someone new and returns their details', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['email' => ' Ada@Example.COM ']))
            ->assertCreated()
            ->assertJsonPath('data.updated', false)
            ->assertJsonPath('data.registration', [
                'full_name' => 'Ada Obi', 'gender' => 'Female', 'email' => 'ada@example.com', 'phone_number' => '08012345678',
                'whatsapp_number' => '08087654321', 'game' => 'Archery', 'menu' => 'Rice and Chicken', 'support_amount' => 5000,
                'children' => [],
            ]);

        $saved = HangoutRegistration::sole();
        expect($saved->email)->toBe('ada@example.com')->and($saved->menu)->toBe('Rice and Chicken')->and($saved->gender)->toBe('Female');
    });

    it('registers the children an adult is bringing, with no email or phone of their own', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [
            hangoutChild(),
            hangoutChild(['full_name' => 'Kemi Obi', 'gender' => 'Female', 'game' => 'Horse Riding', 'menu' => 'Abula and Beef']),
        ]]))
            ->assertCreated()
            ->assertJsonPath('data.registration.children', [
                ['full_name' => 'Tobi Obi', 'gender' => 'Male', 'game' => 'Kids Power Bike', 'menu' => 'Rice and Chicken'],
                ['full_name' => 'Kemi Obi', 'gender' => 'Female', 'game' => 'Horse Riding', 'menu' => 'Abula and Beef'],
            ]);

        expect(HangoutRegistration::sole()->children)->toHaveCount(2);
    });

    it('replaces the children when they are sent again, and keeps them when left out', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [hangoutChild(), hangoutChild(['full_name' => 'Kemi Obi'])]]))
            ->assertCreated();

        // Left out: the saved children stay.
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Bowling']))
            ->assertOk()
            ->assertJsonCount(2, 'data.registration.children');

        // Sent: they replace what was saved.
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [hangoutChild(['full_name' => 'Dele Obi', 'game' => 'Football'])]]))
            ->assertOk()
            ->assertJsonPath('data.registration.children.0.full_name', 'Dele Obi')
            ->assertJsonCount(1, 'data.registration.children');

        // An empty list removes them all.
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => []]))->assertOk();
        expect(HangoutRegistrationChild::count())->toBe(0);
    });

    it('keeps the children unchanged when the phone number is wrong', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [hangoutChild()]]))->assertCreated();

        $this->postJson('/api/hangout/registrations', hangoutPayload(['phone_number' => '08000000001', 'children' => []]))
            ->assertUnprocessable();

        expect(HangoutRegistrationChild::count())->toBe(1);
    });

    it('lets only children pick a children-only game', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Kids Power Bike']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('game');

        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [hangoutChild(['game' => 'Kids Power Bike'])]]))
            ->assertCreated();
    });

    it('checks each child’s details', function (mixed $children, string $field) {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => $children]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(HangoutRegistration::count())->toBe(0);
    })->with([
        'no name'        => [[hangoutChild(['full_name' => ''])], 'children.0.full_name'],
        'no gender'      => [[hangoutChild(['gender' => null])], 'children.0.gender'],
        'unknown game'   => [[hangoutChild(['game' => 'Golf'])], 'children.0.game'],
        'no meal'        => [[hangoutChild(['menu' => ''])], 'children.0.menu'],
        'second child'   => [[hangoutChild(), hangoutChild(['menu' => 'Pizza'])], 'children.1.menu'],
        'too many'       => [array_fill(0, 11, hangoutChild()), 'children'],
        'not a list'     => ['Tobi', 'children'],
        'extra fields'   => [[hangoutChild(['email' => 'tobi@example.com'])], 'children.0'],
    ]);

    it('makes support and WhatsApp optional', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['support_amount' => null, 'whatsapp_number' => null]))
            ->assertCreated();

        expect(HangoutRegistration::sole()->support_amount)->toBeNull();
    });

    it('updates the registration when the same email and phone number are used again', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $this->postJson('/api/hangout/registrations', [
            'full_name'    => 'Ada Obi-Johnson',
            'gender'       => 'Female',
            'email'        => 'ADA@example.com',
            'phone_number' => '+234 801 234 5678', // the same number, written differently
            'game'         => 'Bowling',
            'menu'         => 'Abula and Beef',
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', true)
            ->assertJsonPath('data.registration.menu', 'Abula and Beef')
            ->assertJsonPath('message', 'Your registration has been updated.');

        $saved = HangoutRegistration::sole();
        expect($saved->full_name)->toBe('Ada Obi-Johnson')
            ->and($saved->game)->toBe('Bowling')
            ->and($saved->menu)->toBe('Abula and Beef')
            // Optional fields left empty keep what was saved before.
            ->and($saved->whatsapp_number)->toBe('08087654321')
            ->and((float) $saved->support_amount)->toBe(5000.0);
    });

    it('will not let someone change a registration without its phone number', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $this->postJson('/api/hangout/registrations', hangoutPayload([
            'full_name' => 'Someone Else', 'phone_number' => '08000000001', 'game' => 'Board Games',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number' => HangoutRegistrationService::PHONE_MISMATCH]);

        expect(HangoutRegistration::sole())->full_name->toBe('Ada Obi')->game->toBe('Archery');
    });

    it('changes the phone number when the old one is given as current_phone', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $this->postJson('/api/hangout/registrations', hangoutPayload([
            'phone_number' => '07099999999', 'current_phone' => '0801 234 5678',
        ]))->assertOk();

        expect(HangoutRegistration::sole()->phone_number)->toBe('07099999999');
    });

    it('replaces the pledge when a new amount is given', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();
        $this->postJson('/api/hangout/registrations', hangoutPayload(['support_amount' => '7500.50']))->assertOk();

        expect((float) HangoutRegistration::sole()->support_amount)->toBe(7500.5);
    });

    it('validates the details', function (array $overrides, string $field) {
        $this->postJson('/api/hangout/registrations', hangoutPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(HangoutRegistration::count())->toBe(0);
    })->with([
        'no name'             => [['full_name' => ''], 'full_name'],
        'bad email'           => [['email' => 'not-an-email'], 'email'],
        'no phone'            => [['phone_number' => ''], 'phone_number'],
        'bad phone'           => [['phone_number' => 'call me'], 'phone_number'],
        'bad whatsapp'        => [['whatsapp_number' => 'abc'], 'whatsapp_number'],
        'no game'             => [['game' => ''], 'game'],
        'unknown game'        => [['game' => 'Golf'], 'game'],
        'removed game'        => [['game' => 'Basketball Shootout'], 'game'],
        'children-only game'  => [['game' => 'Kids Power Bike'], 'game'],
        'no meal'             => [['menu' => ''], 'menu'],
        'unknown meal'        => [['menu' => 'Pizza'], 'menu'],
        'no gender'           => [['gender' => ''], 'gender'],
        'unknown gender'      => [['gender' => 'Other'], 'gender'],
        'bad current phone'   => [['current_phone' => 'old one'], 'current_phone'],
        'zero support'        => [['support_amount' => 0], 'support_amount'],
        'negative support'    => [['support_amount' => -50], 'support_amount'],
        'non-numeric support' => [['support_amount' => 'five'], 'support_amount'],
        'three decimals'      => [['support_amount' => 10.555], 'support_amount'],
    ]);

    it('stops taking registrations once the hangout has started', function () {
        $this->travelTo('2026-11-28 10:00:01');

        $this->getJson('/api/hangout')->assertJsonPath('data.registration_open', false);
        $this->postJson('/api/hangout/registrations', hangoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Registration for the hangout has closed.');

        expect(HangoutRegistration::count())->toBe(0);
    });
});

describe('finding your registration', function () {
    it('finds it by email alone, in any case', function () {
        seedHangoutRegistrations();

        $this->postJson('/api/hangout/registrations/lookup', ['email' => ' DAYO@example.com'])
            ->assertOk()
            ->assertJsonCount(1, 'data.registrations')
            ->assertJsonPath('data.registrations.0.full_name', 'Dayo Lawal')
            ->assertJsonPath('data.registrations.0.phone_number', '08044444444')
            ->assertJsonPath('data.registrations.0.game', 'Board Games')
            ->assertJsonPath('data.registrations.0.gender', null)
            // Registered before meals were added: they still need to pick one.
            ->assertJsonPath('data.registrations.0.menu', null);
    });

    it('brings back the children on the registration', function () {
        seedHangoutRegistrations();

        $this->postJson('/api/hangout/registrations/lookup', ['email' => 'ada@example.com'])
            ->assertOk()
            ->assertJsonPath('data.registrations.0.children.*.full_name', ['Tobi Obi', 'Kemi Obi'])
            ->assertJsonPath('data.registrations.0.children.1', [
                'full_name' => 'Kemi Obi', 'gender' => 'Female', 'game' => 'Kids Power Bike', 'menu' => 'Abula and Beef',
            ]);
    });

    it('finds it by phone number alone, however it is written', function (string $phone) {
        seedHangoutRegistrations();

        $this->postJson('/api/hangout/registrations/lookup', ['phone_number' => $phone])
            ->assertOk()
            ->assertJsonCount(1, 'data.registrations')
            ->assertJsonPath('data.registrations.0.email', 'dayo@example.com');
    })->with(['08044444444', '0804 444 4444', '+234 804 444 4444', '234-804-444-4444']);

    it('finds everyone who registered with a shared phone number, newest first', function () {
        seedHangoutRegistrations();
        HangoutRegistration::create(hangoutPayload(['full_name' => 'Tobi Lawal', 'email' => 'tobi@example.com', 'phone_number' => '+234 804 444 4444']));

        $this->postJson('/api/hangout/registrations/lookup', ['phone_number' => '08044444444'])
            ->assertOk()
            ->assertJsonPath('data.registrations.*.full_name', ['Tobi Lawal', 'Dayo Lawal']);
    });

    it('needs both to match when both are given', function () {
        seedHangoutRegistrations();

        $this->postJson('/api/hangout/registrations/lookup', ['email' => 'dayo@example.com', 'phone_number' => '08044444444'])
            ->assertOk()
            ->assertJsonPath('data.registrations.0.full_name', 'Dayo Lawal');
        $this->postJson('/api/hangout/registrations/lookup', ['email' => 'dayo@example.com', 'phone_number' => '08000000000'])
            ->assertNotFound();
    });

    it('gives the same answer for an unknown email or phone number', function () {
        seedHangoutRegistrations();

        $email = $this->postJson('/api/hangout/registrations/lookup', ['email' => 'nobody@example.com'])->assertNotFound()->json('message');
        $phone = $this->postJson('/api/hangout/registrations/lookup', ['phone_number' => '08099999999'])->assertNotFound()->json('message');

        expect($email)->toBe($phone)->toContain('email or phone number');
    });

    it('needs an email or a whole phone number', function (array $payload, string $field) {
        $this->postJson('/api/hangout/registrations/lookup', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'nothing'         => [[], 'email'],
        'bad email'       => [['email' => 'not-an-email'], 'email'],
        'part of a phone' => [['phone_number' => '4444 4444'], 'phone_number'],
        'not a phone'     => [['phone_number' => 'call me'], 'phone_number'],
    ]);

    it('lets a member who registered before meals and gender were asked add them', function () {
        seedHangoutRegistrations();
        $update = [
            'full_name' => 'Dayo Lawal', 'email' => 'dayo@example.com', 'phone_number' => '08044444444',
            'game' => 'Board Games', 'menu' => 'Abula and Beef',
        ];

        // Gender is required on an update too.
        $this->postJson('/api/hangout/registrations', $update)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gender' => 'Choose your gender.']);

        $this->postJson('/api/hangout/registrations', $update + ['gender' => 'Male'])
            ->assertOk()
            ->assertJsonPath('data.registration.gender', 'Male');

        expect(HangoutRegistration::where('email', 'dayo@example.com')->sole())
            ->menu->toBe('Abula and Beef')
            ->gender->toBe('Male')
            ->support_amount->not->toBeNull();
    });
});

describe('admin registrations list', function () {
    it('is only for admins', function () {
        $this->getJson('/api/admin/hangout/registrations')->assertUnauthorized();
    });

    it('turns away signed-in members who are not admins', function () {
        $this->withToken(hangoutUserToken(RoleEnum::MEMBER->value))
            ->getJson('/api/admin/hangout/registrations')
            ->assertForbidden();
    });

    it('lists every detail, newest first, with totals, meals and the cost estimate', function () {
        useHangoutPrices();
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 4)
            ->assertJsonPath('data.registrations.0.full_name', 'Dayo Lawal')
            ->assertJsonPath('data.registrations.0.menu', null)
            ->assertJsonPath('data.registrations.1.full_name', 'Ada Obi')
            ->assertJsonPath('data.registrations.1.children.*.full_name', ['Tobi Obi', 'Kemi Obi'])
            ->assertJsonPath('data.registrations.0.children', [])
            ->assertJsonPath('data.registrations.2.menu', 'Rice and Chicken')
            ->assertJsonPath('data.registrations.2.whatsapp_number', '08022222222')
            ->assertJsonPath('data.registrations.2.gender', 'Female')
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.summary.adults', 4)
            ->assertJsonPath('data.summary.children', 2)
            ->assertJsonPath('data.summary.people', 6)
            ->assertJsonPath('data.summary.pledges', 3)
            ->assertJsonPath('data.summary.total_pledged', 12500)
            // Meals and gender count everyone: adults and children.
            ->assertJsonPath('data.summary.by_menu', [
                ['name' => 'Rice and Chicken', 'count' => 3, 'adults' => 2, 'children' => 1],
                ['name' => 'Abula and Beef', 'count' => 2, 'adults' => 1, 'children' => 1],
            ])
            ->assertJsonPath('data.summary.by_gender', [
                ['name' => 'Male', 'count' => 2, 'adults' => 1, 'children' => 1],
                ['name' => 'Female', 'count' => 3, 'adults' => 2, 'children' => 1],
            ])
            ->assertJsonPath('data.summary.without_gender', 1)
            ->assertJsonPath('data.summary.without_menu', 1);

        $byGame = collect($response->json('data.summary.by_game'))->keyBy('game');
        expect($byGame)->toHaveCount(count(config('hangout.games')))
            ->and($byGame['Archery'])->toMatchArray(['count' => 2, 'adults' => 2, 'children' => 0])
            ->and($byGame['Horse Riding'])->toMatchArray(['count' => 1, 'adults' => 0, 'children' => 1]);

        // Game fees, each age at its own price: Paintball ₦3,000 × 1 adult, Archery ₦4,000 × 2
        // adults, Horse Riding ₦500 × 1 child, Kids Power Bike ₦1,000 × 1 child. Board Games has
        // no price. Gate fees: ₦1,500 × 4 adults + ₦1,000 × 2 children.
        $estimate = $response->json('data.summary.estimate');
        $game = fn(string $name) => collect($estimate['games'])->firstWhere('game', $name);
        expect($game('Paintball'))->toMatchArray(['adults' => 1, 'children' => 0, 'adult_price' => 3000, 'child_price' => 3000, 'subtotal' => 3000])
            ->and($game('Archery'))->toMatchArray(['adults' => 2, 'adult_price' => 4000, 'child_price' => 2000, 'subtotal' => 8000, 'confirmed' => true])
            ->and($game('Horse Riding'))->toMatchArray(['adults' => 0, 'children' => 1, 'adult_price' => 1000, 'child_price' => 500, 'subtotal' => 500])
            ->and($game('Kids Power Bike'))->toMatchArray(['children' => 1, 'adult_price' => null, 'child_price' => 1000, 'subtotal' => 1000, 'unpriced' => 0])
            ->and($game('Bowling'))->toMatchArray(['count' => 0, 'adult_price' => 500, 'subtotal' => 0, 'confirmed' => false])
            ->and($game('Board Games'))->toMatchArray(['adults' => 1, 'adult_price' => null, 'child_price' => null, 'subtotal' => null, 'unpriced' => 1])
            ->and($estimate['games_total'])->toEqual(12500)
            ->and($estimate['entry_fee'])->toMatchArray([
                'adult_price' => 1500, 'child_price' => 1000, 'adults' => 4, 'children' => 2, 'total' => 8000, 'confirmed' => true,
            ])
            ->and($estimate['total'])->toEqual(20500)
            ->and($estimate['unpriced_players'])->toBe(1);
    });

    it('prices every game as Agodi Gardens confirmed, adults and children', function () {
        $hangout = app(HangoutRegistrationService::class);
        $prices = config('hangout.game_prices');

        expect(config('hangout.entry_fee'))->toMatchArray(['amount' => 1500, 'confirmed' => true])
            ->and([$hangout->gamePrice('Paintball', 'adult'), $hangout->gamePrice('Paintball', 'child')])->toBe([3500.0, 3500.0])
            ->and($hangout->gamePrice('Archery', 'adult'))->toBe(2000.0)
            ->and([$hangout->gamePrice('Horse Riding', 'adult'), $hangout->gamePrice('Horse Riding', 'child')])->toBe([1000.0, 500.0])
            ->and([$hangout->gamePrice('VR Games', 'adult'), $hangout->gamePrice('VR Games', 'child')])->toBe([2500.0, 1000.0])
            ->and([$hangout->gamePrice('Trampoline & Bouncing Castle', 'adult'), $hangout->gamePrice('Trampoline & Bouncing Castle', 'child')])->toBe([1000.0, 500.0])
            ->and([$hangout->gamePrice('Kids Power Bike', 'adult'), $hangout->gamePrice('Kids Power Bike', 'child')])->toBe([null, 1000.0])
            ->and([$hangout->gamePrice('Table Tennis', 'adult'), $hangout->gamePrice('Badminton', 'adult')])->toBe([500.0, 1000.0])
            ->and($hangout->gamePrice('Football', 'child'))->toBe(0.0)
            ->and($hangout->gamePrice('Sip & Paint', 'adult'))->toBe(8500.0);
        // Every game has a confirmed price, and every price is for a game people can pick.
        expect(array_keys($prices))->toEqualCanonicalizing(config('hangout.games'))
            ->and(collect($prices)->every(fn(array $price) => $price['confirmed'] === true))->toBeTrue()
            ->and($hangout->childrenOnlyGames())->toBe(['Kids Power Bike'])
            ->and($hangout->adultGameNames())->not->toContain('Kids Power Bike');
    });

    it('moves registrations saved under old game names to the current ones', function () {
        HangoutRegistration::create(hangoutPayload(['game' => 'Paint & Sip']));
        HangoutRegistration::create(hangoutPayload(['email' => 'b@example.com', 'game' => 'Basketball Shootout']));
        HangoutRegistration::create(hangoutPayload(['email' => 'c@example.com', 'game' => 'Archery']));

        (require database_path('migrations/2026_11_18_100100_rename_old_hangout_games.php'))->up();

        expect(HangoutRegistration::orderBy('id')->pluck('game')->all())->toBe(['Sip & Paint', 'Basketball', 'Archery']);
    });

    it('filters by search, game, meal and support', function (array $query, array $names) {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations?' . http_build_query($query + ['sort' => 'full_name', 'direction' => 'asc']))
            ->assertOk();

        expect(collect($response->json('data.registrations'))->pluck('full_name')->all())->toBe($names);
    })->with([
        'name'               => [['search' => 'ola'], ['Bola Ade']],
        'a child’s name'     => [['search' => 'tobi'], ['Ada Obi']],
        'email'              => [['search' => 'dayo@'], ['Dayo Lawal']],
        'phone'              => [['search' => '0803333'], ['Ada Obi']],
        'game'               => [['game' => 'Archery'], ['Ada Obi', 'Bola Ade']],
        'meal'               => [['menu' => 'Rice and Chicken'], ['Ada Obi', 'Bola Ade']],
        'no meal yet'        => [['menu' => 'none'], ['Dayo Lawal']],
        'gender'             => [['gender' => 'Female'], ['Ada Obi', 'Bola Ade']],
        'no gender yet'      => [['gender' => 'none'], ['Dayo Lawal']],
        'with support'       => [['support' => 'with'], ['Ada Obi', 'Bola Ade', 'Dayo Lawal']],
        'without support'    => [['support' => 'without'], ['Chidi Okafor']],
        'combined'           => [['game' => 'Archery', 'support' => 'with', 'search' => 'ada'], ['Ada Obi']],
        'percent is literal' => [['search' => '%'], []],
    ]);

    it('sorts by pledge and by meal with the empty ones last', function (string $sort, string $direction, array $names) {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson("/api/admin/hangout/registrations?sort={$sort}&direction={$direction}")
            ->assertOk();

        expect(collect($response->json('data.registrations'))->pluck('full_name')->all())->toBe($names);
    })->with([
        'pledge, highest first' => ['support_amount', 'desc', ['Ada Obi', 'Bola Ade', 'Dayo Lawal', 'Chidi Okafor']],
        'pledge, lowest first'  => ['support_amount', 'asc', ['Dayo Lawal', 'Bola Ade', 'Ada Obi', 'Chidi Okafor']],
        'meal, A to Z'          => ['menu', 'asc', ['Chidi Okafor', 'Bola Ade', 'Ada Obi', 'Dayo Lawal']],
        'gender, A to Z'        => ['gender', 'asc', ['Bola Ade', 'Ada Obi', 'Chidi Okafor', 'Dayo Lawal']],
    ]);

    it('paginates', function () {
        seedHangoutRegistrations();

        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations?per_page=3&page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonCount(1, 'data.registrations')
            ->assertJsonPath('data.meta.links.0.label', '&laquo; Previous');
    });

    it('rejects unknown sort fields', function () {
        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations?sort=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    });
});

describe('admin Excel export', function () {
    it('is only for admins', function () {
        $this->get('/api/admin/hangout/registrations/export', ['Accept' => 'application/json'])->assertUnauthorized();
    });

    it('exports every detail of the filtered registrations, plus a summary sheet', function () {
        useHangoutPrices();
        seedHangoutRegistrations();
        HangoutRegistration::create(hangoutPayload([
            'full_name' => '=HYPERLINK("http://evil.test")', 'email' => 'evil@example.com', 'phone_number' => '07055555555',
        ]));

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->get('/api/admin/hangout/registrations/export?game=Archery&sort=full_name&direction=asc')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('gccc-hangout-2026-registrations-2026-11-01.xlsx');

        $parts = readXlsxParts($response->getContent());
        expect(array_keys($parts))->toContain('[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet3.xml')
            ->and($parts['xl/workbook.xml'])->toContain('name="Registrations"')->toContain('name="Children"')->toContain('name="Summary"');

        $sheet = $parts['xl/worksheets/sheet1.xml'];
        expect(substr_count($sheet, '<row '))->toBe(4) // header + Ada, Bola and the formula-looking name
            ->and($sheet)->toContain('>Full name<')->toContain('>Gender<')->toContain('>Female<')->toContain('>Meal<')->toContain('>Game price (₦)<')
            ->toContain('>Ada Obi<')->toContain('>ada@example.com<')
            // Phone numbers keep their leading zero as text.
            ->toContain('<t xml:space="preserve">08033333333</t>')
            ->toContain('>Rice and Chicken<')
            // Text is never a formula, and is escaped.
            ->toContain('=HYPERLINK(&quot;http://evil.test&quot;)')
            ->not->toContain('<f>')
            ->not->toContain('Chidi');

        // The children of the filtered registrations, with their parent's contact.
        $children = $parts['xl/worksheets/sheet2.xml'];
        expect(substr_count($children, '<row '))->toBe(3)
            ->and($children)->toContain('>Child’s name<')->toContain('>Parent or guardian<')
            ->toContain('>Tobi Obi<')->toContain('>Kids Power Bike<')->toContain('>Ada Obi<')
            ->toContain('<t xml:space="preserve">08033333333</t>')
            ->toContain('<v>500</v>'); // Tobi's horse ride at the children's price

        // The summary covers everyone. Game fees: Paintball ₦3,000 × 1, Archery ₦4,000 × 3
        // adults (Bola, Ada and the formula-looking name), Horse Riding ₦500 × 1 child, Kids
        // Power Bike ₦1,000 × 1 child = ₦16,500. Gate: ₦1,500 × 5 adults + ₦1,000 × 2 children.
        expect($parts['xl/worksheets/sheet3.xml'])
            ->toContain('>Game fees total<')->toContain('<v>16500</v>')
            ->toContain('>Gate fees total<')->toContain('<v>9500</v>')
            ->toContain('>Estimated total<')->toContain('<v>26000</v>')
            ->toContain('>People<')->toContain('>7 in all.<')
            ->toContain('>Not chosen yet<')
            ->toContain('>Not given<');
    });
});
