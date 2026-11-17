<?php

use App\Enums\RoleEnum;
use App\Models\HangoutRegistration;
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
        ['Chidi Okafor', 'chidi@example.com', '08011111111', null, 'Paintball', 'Abula and Beef', null, '2026-10-01'],
        ['Bola Ade', 'bola@example.com', '08022222222', '08022222222', 'Archery', 'Rice and Chicken', 2000, '2026-10-02'],
        ['Ada Obi', 'ada@example.com', '08033333333', null, 'Archery', 'Rice and Chicken', 10000, '2026-10-03'],
        // Registered before meals were added.
        ['Dayo Lawal', 'dayo@example.com', '08044444444', null, 'Board Games', null, 500, '2026-10-04'],
    ];
    foreach ($rows as [$name, $email, $phone, $wa, $game, $menu, $amount, $date]) {
        $registration = HangoutRegistration::create([
            'full_name' => $name, 'email' => $email, 'phone_number' => $phone,
            'whatsapp_number' => $wa, 'game' => $game, 'menu' => $menu, 'support_amount' => $amount,
        ]);
        $registration->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
    }
}

/** Known prices, so the estimate tests don't depend on what the config says today. */
function useHangoutPrices(): void
{
    config([
        'hangout.entry_fee' => ['amount' => 1500, 'confirmed' => true, 'source' => 'Hangout team.'],
        'hangout.game_prices' => [
            'Paintball' => ['amount' => 3000, 'confirmed' => true, 'source' => 'Hangout team.'],
            'Archery'   => ['amount' => 4000, 'confirmed' => true, 'source' => 'Hangout team.'],
            'Bowling'   => ['amount' => 500, 'confirmed' => false, 'source' => 'An online summary.'],
        ],
    ]);
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
            ->assertJsonCount(10, 'data.games')
            ->assertJsonPath('data.games.1', ['name' => 'Archery', 'registrations_count' => 1])
            ->assertJsonPath('data.menus', [['name' => 'Rice and Chicken'], ['name' => 'Abula and Beef']])
            ->assertJsonPath('data.support_account.account_number', '9018140371')
            ->assertJsonMissingPath('data.estimate')
            ->assertJsonMissingPath('data.entry_fee');

        expect($response->getContent())->not->toContain('price')->not->toContain('source');
    });

    it('counts only games that are still on the list', function () {
        HangoutRegistration::create(hangoutPayload(['game' => 'Snooker']));

        $games = collect($this->getJson('/api/hangout')->json('data.games'));

        expect($games)->toHaveCount(10)
            ->and($games->pluck('name'))->not->toContain('Snooker')
            ->and($games->sum('registrations_count'))->toBe(0);
    });
});

describe('public registration', function () {
    it('registers someone new and returns their details', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['email' => ' Ada@Example.COM ']))
            ->assertCreated()
            ->assertJsonPath('data.updated', false)
            ->assertJsonPath('data.registration', [
                'full_name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone_number' => '08012345678',
                'whatsapp_number' => '08087654321', 'game' => 'Archery', 'menu' => 'Rice and Chicken', 'support_amount' => 5000,
            ]);

        $saved = HangoutRegistration::sole();
        expect($saved->email)->toBe('ada@example.com')->and($saved->menu)->toBe('Rice and Chicken');
    });

    it('makes support and WhatsApp optional', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['support_amount' => null, 'whatsapp_number' => null]))
            ->assertCreated();

        expect(HangoutRegistration::sole()->support_amount)->toBeNull();
    });

    it('updates the registration when the same email and phone number are used again', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $this->postJson('/api/hangout/registrations', [
            'full_name'    => 'Ada Obi-Johnson',
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
        'no meal'             => [['menu' => ''], 'menu'],
        'unknown meal'        => [['menu' => 'Pizza'], 'menu'],
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
            // Registered before meals were added: they still need to pick one.
            ->assertJsonPath('data.registrations.0.menu', null);
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

    it('lets a member who registered before meals were added choose one', function () {
        seedHangoutRegistrations();

        $this->postJson('/api/hangout/registrations', [
            'full_name' => 'Dayo Lawal', 'email' => 'dayo@example.com', 'phone_number' => '08044444444',
            'game' => 'Board Games', 'menu' => 'Abula and Beef',
        ])->assertOk();

        expect(HangoutRegistration::where('email', 'dayo@example.com')->sole())
            ->menu->toBe('Abula and Beef')
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
            ->assertJsonPath('data.registrations.2.menu', 'Rice and Chicken')
            ->assertJsonPath('data.registrations.2.whatsapp_number', '08022222222')
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.summary.pledges', 3)
            ->assertJsonPath('data.summary.total_pledged', 12500)
            ->assertJsonPath('data.summary.by_menu', [['name' => 'Rice and Chicken', 'count' => 2], ['name' => 'Abula and Beef', 'count' => 1]])
            ->assertJsonPath('data.summary.without_menu', 1);

        $byGame = collect($response->json('data.summary.by_game'))->pluck('count', 'game');
        expect($byGame)->toHaveCount(10)->and($byGame['Archery'])->toBe(2);

        // Game fees: Paintball ₦3,000 × 1 + Archery ₦4,000 × 2; Board Games has no price.
        // Gate fees: ₦1,500 × 4. Each total separately, then together.
        $estimate = $response->json('data.summary.estimate');
        $game = fn(string $name) => collect($estimate['games'])->firstWhere('game', $name);
        expect($game('Paintball'))->toMatchArray(['count' => 1, 'price' => 3000, 'subtotal' => 3000, 'confirmed' => true])
            ->and($game('Archery'))->toMatchArray(['count' => 2, 'price' => 4000, 'subtotal' => 8000, 'confirmed' => true])
            ->and($game('Bowling'))->toMatchArray(['count' => 0, 'price' => 500, 'subtotal' => 0, 'confirmed' => false])
            ->and($game('Board Games'))->toMatchArray(['count' => 1, 'price' => null, 'subtotal' => null])
            ->and($estimate['games_total'])->toEqual(11000)
            ->and($estimate['entry_fee'])->toMatchArray(['price' => 1500, 'total' => 6000, 'confirmed' => true])
            ->and($estimate['total'])->toEqual(17000)
            ->and($estimate['unpriced_players'])->toBe(1);
    });

    it('prices the hangout as the team set it', function () {
        expect(config('hangout.entry_fee'))->toMatchArray(['amount' => 1500, 'confirmed' => true])
            ->and(config('hangout.game_prices.Paintball'))->toMatchArray(['amount' => 3000, 'confirmed' => true])
            ->and(config('hangout.game_prices.Archery'))->toMatchArray(['amount' => 4000, 'confirmed' => true]);
        // Every priced game is one people can pick.
        expect(array_diff(array_keys(config('hangout.game_prices')), config('hangout.games')))->toBe([]);
    });

    it('filters by search, game, meal and support', function (array $query, array $names) {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations?' . http_build_query($query + ['sort' => 'full_name', 'direction' => 'asc']))
            ->assertOk();

        expect(collect($response->json('data.registrations'))->pluck('full_name')->all())->toBe($names);
    })->with([
        'name'               => [['search' => 'ola'], ['Bola Ade']],
        'email'              => [['search' => 'dayo@'], ['Dayo Lawal']],
        'phone'              => [['search' => '0803333'], ['Ada Obi']],
        'game'               => [['game' => 'Archery'], ['Ada Obi', 'Bola Ade']],
        'meal'               => [['menu' => 'Rice and Chicken'], ['Ada Obi', 'Bola Ade']],
        'no meal yet'        => [['menu' => 'none'], ['Dayo Lawal']],
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
        expect(array_keys($parts))->toContain('[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml')
            ->and($parts['xl/workbook.xml'])->toContain('name="Registrations"')->toContain('name="Summary"');

        $sheet = $parts['xl/worksheets/sheet1.xml'];
        expect(substr_count($sheet, '<row '))->toBe(4) // header + Ada, Bola and the formula-looking name
            ->and($sheet)->toContain('>Full name<')->toContain('>Meal<')->toContain('>Game price (₦)<')
            ->toContain('>Ada Obi<')->toContain('>ada@example.com<')
            // Phone numbers keep their leading zero as text.
            ->toContain('<t xml:space="preserve">08033333333</t>')
            ->toContain('>Rice and Chicken<')
            // Text is never a formula, and is escaped.
            ->toContain('=HYPERLINK(&quot;http://evil.test&quot;)')
            ->not->toContain('<f>')
            ->not->toContain('Chidi');

        // The summary covers everyone: Paintball 1 and Archery 3 (Bola, Ada and the
        // formula-looking name) at ₦3,000 and ₦4,000; gate ₦1,500 × 5.
        expect($parts['xl/worksheets/sheet2.xml'])
            ->toContain('>Game fees total<')->toContain('<v>15000</v>')
            ->toContain('>Gate fees total<')->toContain('<v>7500</v>')
            ->toContain('>Estimated total<')->toContain('<v>22500</v>')
            ->toContain('>Not chosen yet<');
    });
});
