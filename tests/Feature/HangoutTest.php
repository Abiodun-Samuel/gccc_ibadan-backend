<?php

use App\Enums\RoleEnum;
use App\Models\HangoutRegistration;
use App\Models\User;
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
        ['Chidi Okafor', 'chidi@example.com', '08011111111', null, 'Paintball', null, '2026-10-01'],
        ['Bola Ade', 'bola@example.com', '08022222222', '08022222222', 'Archery', 2000, '2026-10-02'],
        ['Ada Obi', 'ada@example.com', '08033333333', null, 'Archery', 10000, '2026-10-03'],
        ['Dayo Lawal', 'dayo@example.com', '08044444444', null, 'Board Games', 500, '2026-10-04'],
    ];
    foreach ($rows as [$name, $email, $phone, $wa, $game, $amount, $date]) {
        $registration = HangoutRegistration::create([
            'full_name' => $name, 'email' => $email, 'phone_number' => $phone,
            'whatsapp_number' => $wa, 'game' => $game, 'support_amount' => $amount,
        ]);
        $registration->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
    }
}

describe('public hangout details', function () {
    it('serves the games, the support account and whether registration is open', function () {
        HangoutRegistration::create(hangoutPayload());

        $response = $this->getJson('/api/hangout')
            ->assertOk()
            ->assertJsonPath('data.title', 'GCCC Hangout 2026')
            ->assertJsonPath('data.registration_open', true)
            ->assertJsonPath('data.registrations_count', 1)
            ->assertJsonCount(10, 'data.games')
            ->assertJsonPath('data.games.0', ['name' => 'Paintball', 'registrations_count' => 0])
            ->assertJsonPath('data.games.1', ['name' => 'Archery', 'registrations_count' => 1])
            ->assertJsonPath('data.support_account', [
                'account_name'        => 'Olajide Israel Okikiola',
                'bank'                => 'Moniepoint',
                'account_number'      => '9018140371',
                'payment_description' => 'GCCC Hangout 2026',
            ]);

        expect(collect($response->json('data.games'))->pluck('name'))->not->toContain('Basketball Shootout');
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
    it('registers someone new', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['email' => ' Ada@Example.COM ']))
            ->assertCreated()
            ->assertJsonPath('data.updated', false)
            ->assertJsonPath('data.full_name', 'Ada Obi')
            ->assertJsonPath('data.game', 'Archery');

        $saved = HangoutRegistration::sole();
        expect($saved->email)->toBe('ada@example.com')
            ->and((float) $saved->support_amount)->toBe(5000.0)
            ->and($saved->whatsapp_number)->toBe('08087654321');
    });

    it('makes support optional', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload(['support_amount' => null, 'whatsapp_number' => null]))
            ->assertCreated();

        expect(HangoutRegistration::sole()->support_amount)->toBeNull();
    });

    it('updates the existing registration when the email is used again', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $this->postJson('/api/hangout/registrations', [
            'full_name'    => 'Ada Obi-Johnson',
            'email'        => 'ADA@example.com',
            'phone_number' => '08099999999',
            'game'         => 'Bowling',
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', true)
            ->assertJsonPath('message', 'Your registration has been updated.');

        $saved = HangoutRegistration::sole();
        expect($saved->full_name)->toBe('Ada Obi-Johnson')
            ->and($saved->phone_number)->toBe('08099999999')
            ->and($saved->game)->toBe('Bowling')
            // Optional fields left empty keep what was saved before.
            ->and($saved->whatsapp_number)->toBe('08087654321')
            ->and((float) $saved->support_amount)->toBe(5000.0);
    });

    it('replaces the pledge when a new amount is given', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();
        $this->postJson('/api/hangout/registrations', hangoutPayload(['support_amount' => '7500.50']))->assertOk();

        expect((float) HangoutRegistration::sole()->support_amount)->toBe(7500.5);
    });

    it('does not reveal a saved registration to whoever submits its email', function () {
        $this->postJson('/api/hangout/registrations', hangoutPayload())->assertCreated();

        $response = $this->postJson('/api/hangout/registrations', [
            'full_name' => 'Someone Else', 'email' => 'ada@example.com',
            'phone_number' => '08000000001', 'game' => 'Board Games',
        ])->assertOk();

        expect(array_keys($response->json('data')))->toEqualCanonicalizing(['updated', 'full_name', 'game'])
            ->and($response->getContent())->not->toContain('08087654321')
            ->not->toContain('5000');
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

describe('admin registrations list', function () {
    it('is only for admins', function () {
        $this->getJson('/api/admin/hangout/registrations')->assertUnauthorized();
    });

    it('turns away signed-in members who are not admins', function () {
        $this->withToken(hangoutUserToken(RoleEnum::MEMBER->value))
            ->getJson('/api/admin/hangout/registrations')
            ->assertForbidden();
    });

    it('lists every detail, newest first, with totals', function () {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 4)
            ->assertJsonPath('data.registrations.0.full_name', 'Dayo Lawal')
            ->assertJsonPath('data.registrations.1.whatsapp_number', null)
            ->assertJsonPath('data.registrations.2.whatsapp_number', '08022222222')
            ->assertJsonPath('data.registrations.2.support_amount', 2000)
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.summary.pledges', 3)
            ->assertJsonPath('data.summary.total_pledged', 12500);

        $byGame = collect($response->json('data.summary.by_game'))->pluck('count', 'game');
        expect($byGame)->toHaveCount(10)
            ->and($byGame['Archery'])->toBe(2)
            ->and($byGame['Paintball'])->toBe(1)
            ->and($byGame['Bowling'])->toBe(0);
    });

    it('filters by search, game and support', function (array $query, array $names) {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson('/api/admin/hangout/registrations?' . http_build_query($query + ['sort' => 'full_name', 'direction' => 'asc']))
            ->assertOk();

        expect(collect($response->json('data.registrations'))->pluck('full_name')->all())->toBe($names);
    })->with([
        'name'            => [['search' => 'ola'], ['Bola Ade']],
        'email'           => [['search' => 'dayo@'], ['Dayo Lawal']],
        'phone'           => [['search' => '0803333'], ['Ada Obi']],
        'game'            => [['game' => 'Archery'], ['Ada Obi', 'Bola Ade']],
        'with support'    => [['support' => 'with'], ['Ada Obi', 'Bola Ade', 'Dayo Lawal']],
        'without support' => [['support' => 'without'], ['Chidi Okafor']],
        'combined'        => [['game' => 'Archery', 'support' => 'with', 'search' => 'ada'], ['Ada Obi']],
        'percent is literal' => [['search' => '%'], []],
    ]);

    it('sorts by pledge with no-pledge registrations last', function (string $direction, array $names) {
        seedHangoutRegistrations();

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->getJson("/api/admin/hangout/registrations?sort=support_amount&direction={$direction}")
            ->assertOk();

        expect(collect($response->json('data.registrations'))->pluck('full_name')->all())->toBe($names);
    })->with([
        'highest first' => ['desc', ['Ada Obi', 'Bola Ade', 'Dayo Lawal', 'Chidi Okafor']],
        'lowest first'  => ['asc', ['Dayo Lawal', 'Bola Ade', 'Ada Obi', 'Chidi Okafor']],
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

describe('admin CSV export', function () {
    it('exports the filtered registrations and neutralises spreadsheet formulas', function () {
        seedHangoutRegistrations();
        HangoutRegistration::create(hangoutPayload([
            'full_name' => '=HYPERLINK("http://evil.test")', 'email' => 'evil@example.com', 'game' => 'Archery',
        ]));

        $response = $this->withToken(hangoutUserToken(RoleEnum::ADMIN->value))
            ->get('/api/admin/hangout/registrations/export?game=Archery&sort=full_name&direction=asc')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $lines = array_values(array_filter(explode("\n", ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));
        expect($lines)->toHaveCount(4)
            ->and($lines[0])->toStartWith('#,"Full name",Email,Phone,WhatsApp,Game')
            ->and($lines[1])->toContain("'=HYPERLINK")
            ->and($lines[2])->toContain('Ada Obi')
            ->and($lines[3])->toContain('Bola Ade')
            ->and(implode("\n", $lines))->not->toContain('Chidi');
    });
});
