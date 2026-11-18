<?php

use App\Enums\RoleEnum;
use App\Models\HangoutPaintballGroup;
use App\Models\HangoutRegistration;
use App\Models\HangoutRegistrationChild;
use App\Models\User;
use App\Services\HangoutRegistrationService;
use App\Services\PaintballGroupService;
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
                'children' => [], 'paintball' => null,
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

    it('moves the old joint "Table Tennis & Badminton" to Table Tennis, adults and children', function () {
        $registration = HangoutRegistration::create(hangoutPayload(['game' => 'Table Tennis & Badminton']));
        $registration->children()->create(hangoutChild(['game' => 'Table Tennis & Badminton']));
        HangoutRegistration::create(hangoutPayload(['email' => 'b@example.com', 'game' => 'Badminton']));

        (require database_path('migrations/2026_11_19_100000_split_table_tennis_and_badminton.php'))->up();

        expect(HangoutRegistration::orderBy('id')->pluck('game')->all())->toBe(['Table Tennis', 'Badminton'])
            ->and(HangoutRegistrationChild::sole()->game)->toBe('Table Tennis')
            ->and(config('hangout.games'))->toContain('Table Tennis', 'Badminton')->not->toContain('Table Tennis & Badminton');
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
        expect(array_keys($parts))->toContain('[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet4.xml')
            ->and($parts['xl/workbook.xml'])->toContain('name="Registrations"')->toContain('name="Children"')
            ->toContain('name="Paintball groups"')->toContain('name="Summary"');

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
        expect($parts['xl/worksheets/sheet4.xml'])
            ->toContain('>Game fees total<')->toContain('<v>16500</v>')
            ->toContain('>Gate fees total<')->toContain('<v>9500</v>')
            ->toContain('>Estimated total<')->toContain('<v>26000</v>')
            ->toContain('>People<')->toContain('>7 in all.<')
            ->toContain('>Not chosen yet<')
            ->toContain('>Not given<');
    });
});

/**
 * Paintball players, registered in order: each entry is a gender ('Female',
 * 'Male' or null). Numbered from `$from`. They have no group until the
 * groups are next read. Returns the registrations.
 */
function paintballPlayers(array $genders, int $from = 0): array
{
    return array_map(fn($gender, int $i) => tap(HangoutRegistration::create(hangoutPayload([
        'full_name' => "Player {$i}", 'email' => "player{$i}@example.com", 'phone_number' => sprintf('0809%07d', $i),
        'game' => 'Paintball', 'gender' => $gender,
    ])), fn($r) => $r->forceFill(['created_at' => now()->addMinutes($i)])->saveQuietly()), $genders, array_map(fn(int $k) => $from + $k, array_keys($genders)));
}

/** Paintball players who register one after another, each given a group as they do. */
function paintballPlayersOneByOne(array $genders, int $from = 0): array
{
    $players = [];
    foreach (array_values($genders) as $i => $gender) {
        $players[] = paintballPlayers([$gender], $from + $i)[0];
        app(PaintballGroupService::class)->assignNewPlayers();
    }

    return $players;
}

/** The name of the group a player (adult or child) is in now, or null. */
function paintballGroupOf(HangoutRegistration|HangoutRegistrationChild $player): ?string
{
    return HangoutPaintballGroup::find($player->fresh()?->paintball_group_id)?->name;
}

/**
 * Back to an anonymous visitor after an admin request in the same test: the
 * public routes are for guests, and `auth:sanctum` keeps the admin signed in
 * for the rest of a test.
 */
function asGuest(Tests\TestCase $test): void
{
    $test->withoutToken();
    auth()->shouldUse('web');
    auth()->forgetGuards();
}

/** Each group's size, in order. */
function paintballSizes(): array
{
    return array_column(app(PaintballGroupService::class)->groups(), 'size');
}

describe('paintball groups', function () {
    it('makes as many groups of at least five as it can', function (int $players, array $sizes) {
        paintballPlayers(array_fill(0, $players, 'Female'));

        expect(array_column(app(PaintballGroupService::class)->groups(), 'size'))->toBe($sizes);
    })->with([
        'nobody'        => [0, []],
        'too few yet'   => [4, [4]],
        'exactly five'  => [5, [5]],
        'nine'          => [9, [9]],
        'ten'           => [10, [5, 5]],
        'twelve'        => [12, [6, 6]],
        'seventeen'     => [17, [6, 6, 5]],
        'thirty-one'    => [31, [6, 5, 5, 5, 5, 5]],
    ]);

    it('mixes women and men evenly across the groups', function () {
        paintballPlayers([...array_fill(0, 7, 'Female'), ...array_fill(0, 8, 'Male'), null]);

        $groups = app(PaintballGroupService::class)->groups();
        expect($groups)->toHaveCount(3);
        foreach (['women', 'men'] as $kind) {
            $counts = array_column($groups, $kind);
            expect(max($counts) - min($counts))->toBeLessThanOrEqual(1);
        }
        expect(array_sum(array_column($groups, 'women')))->toBe(7)
            ->and(array_sum(array_column($groups, 'men')))->toBe(8)
            ->and(max(array_column($groups, 'size')) - min(array_column($groups, 'size')))->toBeLessThanOrEqual(1);
    });

    it('spreads children evenly too', function () {
        $adults = paintballPlayers([...array_fill(0, 4, 'Female'), ...array_fill(0, 4, 'Male')]);
        $adults[0]->children()->createMany([
            hangoutChild(['full_name' => 'Kid A', 'gender' => 'Female', 'game' => 'Paintball']),
            hangoutChild(['full_name' => 'Kid B', 'gender' => 'Male', 'game' => 'Paintball']),
        ]);

        $groups = app(PaintballGroupService::class)->groups();
        expect(array_column($groups, 'children'))->toBe([1, 1])
            ->and(array_column($groups, 'size'))->toBe([5, 5]);
    });

    it('names the groups from the Bible list, in order, starting again with "II"', function () {
        config(['hangout.paintball.group_names' => [
            ['name' => "Gideon's 300", 'reference' => 'Judges 7:7', 'motto' => 'A small band with a big God.'],
            ['name' => 'Jericho Wall Breakers', 'reference' => 'Joshua 6:20', 'motto' => 'Seven laps, one shout.'],
        ]]);
        paintballPlayers(array_fill(0, 15, 'Male'));

        $groups = app(PaintballGroupService::class)->groups();
        expect(array_column($groups, 'name'))->toBe(["Gideon's 300", 'Jericho Wall Breakers', "Gideon's 300 II"])
            ->and($groups[0])->toMatchArray(['number' => 1, 'reference' => 'Judges 7:7', 'motto' => 'A small band with a big God.']);
    });

    it('gives the same groups every time for the same registrations', function () {
        paintballPlayers(['Female', 'Male', 'Male', 'Female', null, 'Female', 'Male', 'Male', 'Female', 'Male', 'Female']);

        $first = app(PaintballGroupService::class)->groups();
        $again = app(PaintballGroupService::class)->groups();
        expect($again)->toBe($first);
    });

    it('tells a member their group and everyone in it when they register', function () {
        paintballPlayers(['Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male']);

        $registration = $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball', 'gender' => 'Female']))
            ->assertCreated()
            ->json('data.registration.paintball');

        // Ten players: two groups of five.
        expect($registration['final'])->toBeFalse()
            ->and($registration['min_group_size'])->toBe(5)
            ->and($registration['groups'])->toHaveCount(1);
        $group = $registration['groups'][0];
        expect($group['name'])->toBeIn(["Gideon's 300", 'Jericho Wall Breakers'])
            ->and($group['size'])->toBe(5)
            ->and($group['members'])->toHaveCount(5)
            ->and(collect($group['members'])->where('yours', true)->pluck('full_name')->all())->toBe(['Ada Obi'])
            // Names only: no one's email or phone number.
            ->and(array_keys($group['members'][0]))->toBe(['full_name', 'gender', 'child', 'yours']);
    });

    it('shows a parent the groups their children are in', function () {
        paintballPlayers(['Male', 'Female', 'Male', 'Female']);

        $paintball = $this->postJson('/api/hangout/registrations', hangoutPayload([
            'game' => 'Archery',
            'children' => [hangoutChild(['full_name' => 'Tobi Obi', 'game' => 'Paintball'])],
        ]))->assertCreated()->json('data.registration.paintball');

        expect($paintball['groups'])->toHaveCount(1)
            ->and(collect($paintball['groups'][0]['members'])->firstWhere('yours', true))
            ->toBe(['full_name' => 'Tobi Obi', 'gender' => 'Male', 'child' => true, 'yours' => true]);
    });

    it('has no paintball groups for someone not playing it', function () {
        paintballPlayers(['Male', 'Female', 'Male', 'Female', 'Male']);

        $this->postJson('/api/hangout/registrations', hangoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.registration.paintball', null);
    });

    it('says the groups are final once registration closes', function () {
        paintballPlayers(['Male', 'Female', 'Male', 'Female', 'Male']);
        $this->travelTo('2026-11-28 10:00:01');

        $this->postJson('/api/hangout/registrations/lookup', ['email' => 'player0@example.com'])
            ->assertOk()
            ->assertJsonPath('data.registrations.0.paintball.final', true);
    });

    it('lists every group for admins, with each member’s contact', function () {
        $adults = paintballPlayers(['Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male', 'Female']);
        $adults[0]->children()->create(hangoutChild(['full_name' => 'Kid A', 'game' => 'Paintball']));

        $groups = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations')
            ->assertOk()
            ->json('data.summary.paintball.groups');

        expect($groups)->toHaveCount(2)
            ->and(array_column($groups, 'size'))->toEqualCanonicalizing([6, 5])
            ->and($groups[0]['id'])->toBeInt();
        $kid = collect($groups)->flatMap(fn($g) => $g['members'])->firstWhere('full_name', 'Kid A');
        expect($kid)->toMatchArray([
            'kind' => 'child', 'id' => $adults[0]->children()->sole()->id,
            'child' => true, 'parent' => 'Player 0', 'phone_number' => '08090000000',
        ]);
        $adult = collect($groups)->flatMap(fn($g) => $g['members'])->firstWhere('full_name', 'Player 3');
        expect($adult)->toMatchArray(['kind' => 'adult', 'id' => $adults[3]->id, 'child' => false, 'parent' => null]);
    });

    it('exports the paintball groups', function () {
        paintballPlayers(['Male', 'Female', 'Male', 'Female', 'Male']);

        $parts = readXlsxParts($this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->get('/api/admin/hangout/registrations/export')->assertOk()->getContent());

        expect($parts['xl/worksheets/sheet3.xml'])
            ->toContain('>1. Gideon&apos;s 300<')
            ->toContain('5 players: 2 women, 3 men')
            ->toContain('>Player 0<')
            ->toContain('>Adult<');
    });
});

describe('kept paintball groups', function () {
    it('gives each new player a group straight away, and never moves them as others join', function () {
        $first = paintballPlayersOneByOne(['Female', 'Male', 'Female', 'Male', 'Female']);
        expect(paintballSizes())->toBe([5])
            ->and(array_map('paintballGroupOf', $first))->toBe(array_fill(0, 5, "Gideon's 300"));

        // Every group has five, so the sixth player starts the next one, and so on.
        $next = paintballPlayersOneByOne(['Male', 'Female', 'Male', 'Female', 'Male', 'Female'], 5);
        expect(paintballSizes())->toBe([5, 5, 1])
            ->and(array_map('paintballGroupOf', $first))->toBe(array_fill(0, 5, "Gideon's 300"))
            ->and(array_map('paintballGroupOf', array_slice($next, 0, 5)))->toBe(array_fill(0, 5, 'Jericho Wall Breakers'))
            ->and(paintballGroupOf($next[5]))->toBe("David's Slingers");
    });

    it('puts a new player in the group still needing players that has the fewest of their gender', function () {
        $service = app(PaintballGroupService::class);
        $service->createGroup();
        $service->createGroup();

        $players = paintballPlayersOneByOne(['Female', 'Female', 'Male', 'Male', 'Female']);

        expect(array_map('paintballGroupOf', $players))
            ->toBe(["Gideon's 300", 'Jericho Wall Breakers', "Gideon's 300", 'Jericho Wall Breakers', "Gideon's 300"]);
    });

    it('arranges players who picked paintball before groups were kept evenly, then keeps them', function () {
        $before = paintballPlayers([...array_fill(0, 6, 'Female'), ...array_fill(0, 5, 'Male')]);

        $groups = app(PaintballGroupService::class)->groups();
        expect(array_column($groups, 'size'))->toEqualCanonicalizing([6, 5])
            ->and(array_column($groups, 'women'))->toBe([3, 3])
            ->and(array_column($groups, 'men'))->toEqualCanonicalizing([3, 2]);
        $kept = array_map('paintballGroupOf', $before);

        paintballPlayersOneByOne(['Male'], 11);
        expect(array_map('paintballGroupOf', $before))->toBe($kept)
            ->and(paintballSizes())->toHaveCount(3);
    });

    it('keeps a member’s group when they update their registration', function () {
        paintballPlayersOneByOne(['Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male', 'Female', 'Male']);

        // The second group still needs a player.
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball']))
            ->assertCreated()
            ->assertJsonPath('data.registration.paintball.groups.0.name', 'Jericho Wall Breakers');

        // More people join; Ada changes her name and meal.
        paintballPlayersOneByOne(['Female', 'Male', 'Female'], 9);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball', 'full_name' => 'Ada Obi-Okeke', 'menu' => 'Abula and Beef']))
            ->assertOk()
            ->assertJsonPath('data.registration.paintball.groups.0.name', 'Jericho Wall Breakers');

        expect(paintballGroupOf(HangoutRegistration::where('email', 'ada@example.com')->sole()))->toBe('Jericho Wall Breakers')
            ->and(paintballSizes())->toBe([5, 5, 3]);
    });

    it('keeps a child’s group when their parent updates the registration', function () {
        paintballPlayersOneByOne(['Male', 'Female', 'Male', 'Female']);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [
            hangoutChild(['full_name' => 'Tobi Obi', 'game' => 'Paintball']),
            hangoutChild(['full_name' => 'Kemi Obi', 'gender' => 'Female']),
        ]]))->assertCreated()->assertJsonPath('data.registration.paintball.groups.0.name', "Gideon's 300");

        // The first group fills up. Ada sends her children again: Tobi with another
        // meal, and Kemi now playing paintball too.
        paintballPlayersOneByOne(['Female', 'Male', 'Female', 'Male', 'Female'], 4);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['children' => [
            hangoutChild(['full_name' => 'TOBI OBI', 'game' => 'Paintball', 'menu' => 'Abula and Beef']),
            hangoutChild(['full_name' => 'Kemi Obi', 'gender' => 'Female', 'game' => 'Paintball']),
        ]]))->assertOk();

        $child = fn(string $name) => HangoutRegistrationChild::where('full_name', $name)->sole();
        expect(paintballGroupOf($child('TOBI OBI')))->toBe("Gideon's 300")
            ->and(paintballGroupOf($child('Kemi Obi')))->toBe("David's Slingers")
            ->and(paintballSizes())->toBe([5, 5, 1]);
    });

    it('takes a player out of their group when they switch game; coming back, they join like anyone new', function () {
        paintballPlayersOneByOne(['Male', 'Female']);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball']))
            ->assertJsonPath('data.registration.paintball.groups.0.name', "Gideon's 300");
        paintballPlayersOneByOne(['Male', 'Female'], 2);

        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Archery']))
            ->assertOk()
            ->assertJsonPath('data.registration.paintball', null);
        expect(paintballSizes())->toBe([4]);

        // Someone new takes the free place, so Ada, coming back, starts the next group.
        paintballPlayersOneByOne(['Female'], 4);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball']))
            ->assertOk()
            ->assertJsonPath('data.registration.paintball.groups.0.name', 'Jericho Wall Breakers');
        expect(paintballSizes())->toBe([5, 1]);
    });
});

describe('admin paintball groups', function () {
    it('is only for admins', function () {
        $group = app(PaintballGroupService::class)->createGroup();
        [$player] = paintballPlayersOneByOne(['Male']);
        $calls = [
            ['postJson', '/api/admin/hangout/paintball/groups'],
            ['postJson', '/api/admin/hangout/paintball/groups/rearrange'],
            ['patchJson', "/api/admin/hangout/paintball/groups/{$group->id}"],
            ['deleteJson', "/api/admin/hangout/paintball/groups/{$group->id}"],
            ['patchJson', "/api/admin/hangout/paintball/players/adult/{$player->id}"],
        ];
        foreach ($calls as [$method, $url]) {
            $this->{$method}($url, ['name' => 'X', 'group_id' => $group->id])->assertUnauthorized();
        }
        $this->withToken(hangoutUserToken())->postJson('/api/admin/hangout/paintball/groups')->assertForbidden();
        expect(HangoutPaintballGroup::count())->toBe(1);
    });

    it('moves a player, adult or child, to another group, where they stay when they update', function () {
        $players = paintballPlayersOneByOne(['Male', 'Female', 'Male', 'Female', 'Male', 'Female']);
        $players[0]->children()->create(hangoutChild(['full_name' => 'Kid A', 'game' => 'Paintball']));
        $service = app(PaintballGroupService::class);
        $service->assignNewPlayers();
        $gideon = HangoutPaintballGroup::where('name', "Gideon's 300")->sole();
        $jericho = HangoutPaintballGroup::where('name', 'Jericho Wall Breakers')->sole();
        $updatedAt = $players[0]->fresh()->updated_at;
        $this->travel(1)->hours();

        $token = hangoutUserToken(RoleEnum::ADMIN->value);
        $groups = $this->withToken($token)
            ->patchJson("/api/admin/hangout/paintball/players/adult/{$players[0]->id}", ['group_id' => $jericho->id])
            ->assertOk()
            ->assertJsonPath('message', 'Moved Player 0 to Jericho Wall Breakers.')
            ->json('data.groups');
        expect(array_column($groups, 'size'))->toBe([4, 3])
            ->and(collect($groups[1]['members'])->pluck('full_name')->all())->toBe(['Player 0', 'Player 5', 'Kid A'])
            // An admin's move isn't the member updating their registration.
            ->and($players[0]->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();

        $kid = $players[0]->children()->sole();
        $this->patchJson("/api/admin/hangout/paintball/players/child/{$kid->id}", ['group_id' => $gideon->id])->assertOk();
        expect(paintballGroupOf($kid))->toBe("Gideon's 300");

        // Player 0 updates, sending Kid A again: both stay where the admin put them.
        asGuest($this);
        $this->postJson('/api/hangout/registrations', hangoutPayload([
            'full_name' => 'Player 0', 'gender' => 'Male', 'email' => 'player0@example.com', 'phone_number' => '08090000000',
            'game' => 'Paintball', 'menu' => 'Abula and Beef',
            'children' => [hangoutChild(['full_name' => 'Kid A', 'game' => 'Paintball'])],
        ]))->assertOk();
        expect(paintballGroupOf($players[0]))->toBe('Jericho Wall Breakers')
            ->and(paintballGroupOf(HangoutRegistrationChild::where('full_name', 'Kid A')->sole()))->toBe("Gideon's 300");
    });

    it('won’t move someone who isn’t playing paintball, or into a group that’s gone', function () {
        $group = app(PaintballGroupService::class)->createGroup();
        $archer = HangoutRegistration::create(hangoutPayload());
        [$player] = paintballPlayersOneByOne(['Male']);
        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value));

        $this->patchJson("/api/admin/hangout/paintball/players/adult/{$archer->id}", ['group_id' => $group->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player');
        $this->patchJson('/api/admin/hangout/paintball/players/child/999999', ['group_id' => $group->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player');
        $this->patchJson("/api/admin/hangout/paintball/players/adult/{$player->id}", ['group_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group_id');
        $this->patchJson("/api/admin/hangout/paintball/players/parent/{$player->id}", ['group_id' => $group->id])->assertNotFound();
        expect($archer->fresh()->paintball_group_id)->toBeNull();
    });

    it('adds a group with the next Bible name not yet used, or the name given', function () {
        app(PaintballGroupService::class)->createGroup();
        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value));

        $added = $this->postJson('/api/admin/hangout/paintball/groups')
            ->assertCreated()
            ->assertJsonPath('message', 'Jericho Wall Breakers added. New players join it until it has enough.')
            ->json('data.groups.1');
        expect($added)->toMatchArray(['number' => 2, 'name' => 'Jericho Wall Breakers', 'reference' => 'Joshua 6:20', 'size' => 0, 'members' => []]);

        $this->postJson('/api/admin/hangout/paintball/groups', ['name' => 'The Peacemakers', 'reference' => 'Matthew 5:9', 'motto' => 'Blessed are they.'])
            ->assertCreated()
            ->assertJsonPath('data.groups.2.name', 'The Peacemakers')
            ->assertJsonPath('data.groups.2.motto', 'Blessed are they.');
        $this->postJson('/api/admin/hangout/paintball/groups', ['name' => 'the peacemakers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Another group already has that name.']);
    });

    it('renames a group, and members see the new name', function () {
        $group = app(PaintballGroupService::class)->createGroup();
        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value));

        $this->patchJson("/api/admin/hangout/paintball/groups/{$group->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Give the group a name.']);
        $saved = $this->patchJson("/api/admin/hangout/paintball/groups/{$group->id}", [
            'name' => 'The Peacemakers', 'reference' => 'Matthew 5:9', 'motto' => 'Blessed are the peacemakers.',
        ])->assertOk()->json('data.groups.0');
        expect($saved)->toMatchArray(['name' => 'The Peacemakers', 'reference' => 'Matthew 5:9', 'motto' => 'Blessed are the peacemakers.']);

        asGuest($this);
        $this->postJson('/api/hangout/registrations', hangoutPayload(['game' => 'Paintball']))
            ->assertCreated()
            ->assertJsonPath('data.registration.paintball.groups.0.name', 'The Peacemakers');
    });

    it('removes a group only once it is empty', function () {
        $players = paintballPlayersOneByOne(['Male', 'Female', 'Male', 'Female', 'Male', 'Female']);
        $gideon = HangoutPaintballGroup::where('name', "Gideon's 300")->sole();
        $jericho = HangoutPaintballGroup::where('name', 'Jericho Wall Breakers')->sole();
        $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value));

        $this->deleteJson("/api/admin/hangout/paintball/groups/{$jericho->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group' => 'Move the players in Jericho Wall Breakers to other groups first.']);

        $this->patchJson("/api/admin/hangout/paintball/players/adult/{$players[5]->id}", ['group_id' => $gideon->id])->assertOk();
        $this->deleteJson("/api/admin/hangout/paintball/groups/{$jericho->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Removed Jericho Wall Breakers.')
            ->assertJsonCount(1, 'data.groups');
        expect(paintballSizes())->toBe([6]);
    });

    it('rearranges everyone into even groups, keeping the groups’ names', function () {
        // One by one, the first group gets five men and the third has one player.
        paintballPlayersOneByOne([...array_fill(0, 6, 'Male'), ...array_fill(0, 5, 'Female')]);
        expect(paintballSizes())->toBe([5, 5, 1]);

        $groups = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->postJson('/api/admin/hangout/paintball/groups/rearrange')
            ->assertOk()
            ->assertJsonPath('message', 'Rearranged everyone into even groups.')
            ->json('data.groups');
        expect(array_column($groups, 'name'))->toBe(["Gideon's 300", 'Jericho Wall Breakers'])
            ->and(array_column($groups, 'size'))->toEqualCanonicalizing([6, 5])
            ->and(array_column($groups, 'men'))->toBe([3, 3])
            ->and(array_column($groups, 'women'))->toEqualCanonicalizing([3, 2])
            ->and(HangoutPaintballGroup::count())->toBe(2);
    });
});

it('arranges paintball groups evenly in every mix: sizes, women, men and children each within one', function () {
    $service = app(PaintballGroupService::class);
    $arrange = (new ReflectionClass($service))->getMethod('arrange');
    $count = fn(array $players, callable $is) => count(array_filter($players, $is));

    for ($women = 0; $women <= 12; $women++) {
        for ($men = 0; $men <= 12; $men++) {
            foreach ([[0, 0], [1, 0], [0, 2], [2, 1], [3, 3]] as [$girls, $boys]) {
                $players = [];
                foreach ([['Female', false, $women], ['Male', false, $men], ['Female', true, $girls], ['Male', true, $boys]] as [$gender, $child, $howMany]) {
                    for ($i = 0; $i < $howMany; $i++) {
                        $n = count($players);
                        $players[] = [
                            'kind' => $child ? 'child' : 'adult', 'id' => $n, 'full_name' => "P{$n}", 'gender' => $gender,
                            'child' => $child, 'registration_id' => $n, 'group_id' => null, 'order' => [$n, 0, $n],
                        ];
                    }
                }

                $groups = array_map(fn(array $members) => [
                    'size'     => count($members),
                    'women'    => $count($members, fn($m) => $m['gender'] === 'Female'),
                    'men'      => $count($members, fn($m) => $m['gender'] === 'Male'),
                    'children' => $count($members, fn($m) => $m['child']),
                ], $arrange->invoke($service, collect($players)));
                if (!$groups) {
                    continue;
                }
                expect(count($groups))->toBe(max(1, intdiv(count($players), 5)));
                foreach (['size', 'women', 'men', 'children'] as $kind) {
                    $counts = array_column($groups, $kind);
                    expect(max($counts) - min($counts))->toBeLessThanOrEqual(1, "{$kind} with {$women}W {$men}M {$girls}g {$boys}b");
                }
                if (count($groups) > 1) {
                    expect(min(array_column($groups, 'size')))->toBeGreaterThanOrEqual(5);
                }
            }
        }
    }
});
