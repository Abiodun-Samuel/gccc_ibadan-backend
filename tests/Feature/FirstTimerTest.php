<?php

use App\Enums\RoleEnum;
use App\Enums\UnitEnum;
use App\Models\Unit;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Every role exists in a real install (seeded); the role scopes fail without them.
beforeEach(function () {
    foreach (RoleEnum::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

// Helpers are prefixed "ft" so they never clash with the global helpers in other test files.

function ftUser(array $attributes = [], array $roles = []): User
{
    $user = User::forceCreate(array_merge([
        'first_name'   => 'Test',
        'last_name'    => 'Person',
        'email'        => uniqid('ft', true) . '@example.com',
        'phone_number' => '080' . random_int(10000000, 99999999),
        'password'     => Hash::make('password'),
    ], $attributes));

    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user;
}

function ftFirstTimer(string $visit, array $extraRoles = [], array $attributes = []): User
{
    return ftUser(
        array_merge(['date_of_visit' => $visit], $attributes),
        array_merge([RoleEnum::FIRST_TIMER->value], $extraRoles)
    );
}

function ftTokenFor(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}

function ftAdminToken(): string
{
    return ftTokenFor(ftUser([], [RoleEnum::ADMIN->value]));
}

function ftFollowUpMember(): User
{
    $user = ftUser([], [RoleEnum::MEMBER->value]);
    $unit = Unit::firstOrCreate(['name' => UnitEnum::FOLLOW_UP->value]);
    $user->units()->attach($unit->id);

    return $user;
}

/**
 * Requests as the token's owner. Tests reuse one app, and the auth guard remembers the last
 * user it resolved, so it is reset before every request made with a different token.
 */
function ftAs(Tests\TestCase $test, string $token): Tests\TestCase
{
    auth()->forgetGuards();

    return $test->withToken($token);
}

/** The ids the first-timers list returns for a query string, as the given user. */
function ftListIds(Tests\TestCase $test, string $token, string $query = ''): array
{
    $response = ftAs($test, $token)->getJson('/api/first-timers' . ($query ? "?{$query}" : ''));
    $response->assertOk();

    return collect($response->json('data'))->pluck('id')->sort()->values()->all();
}

describe('first timers list', function () {
    it('lets admins and the follow-up unit see the list, and nobody else', function () {
        ftFirstTimer('2025-09-01');

        ftAs($this, ftAdminToken())->getJson('/api/first-timers')->assertOk();
        ftAs($this, ftTokenFor(ftFollowUpMember()))->getJson('/api/first-timers')->assertOk();

        ftAs($this, ftTokenFor(ftUser([], [RoleEnum::MEMBER->value])))
            ->getJson('/api/first-timers')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only admins and the follow-up unit can see the first timers list.');
    });

    it('filters by a month range', function () {
        $token = ftAdminToken();
        ftFirstTimer('2025-08-31');
        $start = ftFirstTimer('2025-09-01');
        $end = ftFirstTimer('2026-02-28');
        $after = ftFirstTimer('2026-03-01');

        expect(ftListIds($this, $token, 'visit_from=2025-09-01&visit_to=2026-02-28'))
            ->toBe([$start->id, $end->id]);
        expect(ftListIds($this, $token, 'visit_from=2025-09-01'))
            ->toBe([$start->id, $end->id, $after->id]);
    });

    it('filters a month within a year, and keeps the old month-only behaviour', function () {
        $token = ftAdminToken();
        $sept2025 = ftFirstTimer('2025-09-10');
        $sept2026 = ftFirstTimer('2026-09-10');
        ftFirstTimer('2025-10-10');

        expect(ftListIds($this, $token, 'date_month_of_visit=09&date_year_of_visit=2025'))->toBe([$sept2025->id]);
        expect(ftListIds($this, $token, 'date_month_of_visit=09'))->toBe([$sept2025->id, $sept2026->id]);
    });

    it('rejects a bad range', function () {
        $token = ftAdminToken();

        ftAs($this, $token)->getJson('/api/first-timers?visit_from=2026-03-01&visit_to=2026-02-28')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['visit_to']]);
        ftAs($this, $token)->getJson('/api/first-timers?visit_from=2025-13-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['visit_from']]);
        ftAs($this, $token)->getJson('/api/first-timers?date_month_of_visit=09&date_year_of_visit=1999')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['date_year_of_visit']]);
    });

    it('marks first timers who are now members', function () {
        $member = ftFirstTimer('2025-09-01', [RoleEnum::MEMBER->value]);
        $plain = ftFirstTimer('2025-09-02');

        $rows = collect(ftAs($this, ftAdminToken())->getJson('/api/first-timers')->json('data'))->keyBy('id');

        expect($rows[$member->id]['is_member'])->toBeTrue();
        expect($rows[$plain->id]['is_member'])->toBeFalse();
    });
});

function ftAdmin(): User
{
    return ftUser([], [RoleEnum::ADMIN->value]);
}

function ftDelete(Tests\TestCase $test, string $token, array $ids): Illuminate\Testing\TestResponse
{
    return ftAs($test, $token)->postJson('/api/admin/first-timers/delete', ['ids' => $ids]);
}

/** Gives a first timer an attendance, a follow-up note and a login token, as in real use. */
function ftWithHistory(User $firstTimer, User $noteBy, int $serviceId): User
{
    DB::table('attendances')->insert([
        'user_id' => $firstTimer->id, 'service_id' => $serviceId, 'attendance_date' => '2025-09-07',
        'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('followup_feedbacks')->insert([
        'user_id' => $firstTimer->id, 'created_by' => $noteBy->id, 'type' => 'Others', 'note' => 'Called after service.',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $firstTimer->createToken('phone');

    return $firstTimer;
}

describe('deleting first timers', function () {
    beforeEach(function () {
        $this->mock(UploadService::class);
    });

    it('is for admins only', function () {
        $firstTimer = ftFirstTimer('2025-09-01');

        ftDelete($this, ftTokenFor(ftFollowUpMember()), [$firstTimer->id])->assertForbidden();
        ftDelete($this, ftTokenFor(ftUser([], [RoleEnum::MEMBER->value])), [$firstTimer->id])->assertForbidden();

        expect(User::find($firstTimer->id))->not->toBeNull();
    });

    it('deletes the chosen first timers and everything tied to them', function () {
        $admin = ftAdmin();
        $serviceId = DB::table('services')->insertGetId(['name' => 'Sunday Service', 'created_at' => now(), 'updated_at' => now()]);
        $a = ftWithHistory(ftFirstTimer('2025-09-01'), $admin, $serviceId);
        $b = ftWithHistory(ftFirstTimer('2025-09-08'), $admin, $serviceId);
        $c = ftFirstTimer('2025-09-15');
        $followedByC = ftFirstTimer('2025-09-22', [], ['followup_by_id' => $c->id]);
        $ids = [$a->id, $b->id, $c->id];

        $response = ftDelete($this, ftTokenFor($admin), $ids)
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 3 first timers.')
            ->assertJsonPath('data.skipped', []);

        expect(collect($response->json('data.deleted'))->pluck('id')->all())->toBe($ids);
        expect(User::whereIn('id', $ids)->count())->toBe(0);
        expect(DB::table('attendances')->whereIn('user_id', $ids)->count())->toBe(0);
        expect(DB::table('followup_feedbacks')->whereIn('user_id', $ids)->count())->toBe(0);
        expect(DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $ids)->count())->toBe(0);
        expect(DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->count())->toBe(0);
        expect($followedByC->fresh()->followup_by_id)->toBeNull();
        // The admin, and their own login, are untouched.
        expect(User::find($admin->id))->not->toBeNull();
    });

    it('skips members, admins and ids it cannot find', function () {
        $admin = ftAdmin();
        $member = ftFirstTimer('2025-09-01', [RoleEnum::MEMBER->value], ['first_name' => 'Ada', 'last_name' => 'Obi']);
        $plain = ftFirstTimer('2025-09-02');

        $response = ftDelete($this, ftTokenFor($admin), [$member->id, $admin->id, 999999, $plain->id])
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 1 first timer. Skipped 1 who is now a member: Ada Obi. 2 could not be found.');

        expect(collect($response->json('data.deleted'))->pluck('id')->all())->toBe([$plain->id]);
        expect(collect($response->json('data.skipped'))->map(fn($row) => [$row['id'], $row['reason']])->all())->toBe([
            [$member->id, 'member'],
            [$admin->id, 'not_found'],
            [999999, 'not_found'],
        ]);
        expect(User::find($member->id))->not->toBeNull();
        expect(User::find($admin->id))->not->toBeNull();
        expect(User::find($plain->id))->toBeNull();
    });

    it('can be repeated safely', function () {
        $token = ftAdminToken();
        $firstTimer = ftFirstTimer('2025-09-01');

        ftDelete($this, $token, [$firstTimer->id])->assertOk();
        ftDelete($this, $token, [$firstTimer->id])
            ->assertOk()
            ->assertJsonPath('message', 'No first timers were deleted. 1 could not be found.')
            ->assertJsonPath('data.skipped.0.reason', 'not_found');
    });

    it('limits a delete to 100', function () {
        $token = ftAdminToken();

        ftDelete($this, $token, range(1, 101))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ids.0', 'You can delete up to 100 first timers at a time.');
        ftDelete($this, $token, [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ids.0', 'Choose at least one first timer to delete.');
        ftDelete($this, $token, [5, 5])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['ids.0']]);
    });

    it('removes photos after deleting, and a photo failure does not undo the delete', function () {
        config(['filesystems.disks.cloudinary.cloud' => 'demo']);
        $avatar = 'https://res.cloudinary.com/demo/image/upload/v1700000000/first-timers/face.jpg';
        $secondary = 'https://res.cloudinary.com/demo/image/upload/v1700000001/first-timers/visit.jpg';
        $firstTimer = ftFirstTimer('2025-09-01', [], ['avatar' => $avatar, 'secondary_avatar' => $secondary]);

        $this->mock(UploadService::class, function ($mock) use ($avatar, $secondary) {
            $mock->shouldReceive('delete')->once()->with($avatar)->andThrow(new Exception('Cloudinary is down'));
            $mock->shouldReceive('delete')->once()->with($secondary)->andReturn(true);
        });
        Log::spy();

        ftDelete($this, ftAdminToken(), [$firstTimer->id])->assertOk();

        expect(User::find($firstTimer->id))->toBeNull();
        Log::shouldHaveReceived('error')
            ->withArgs(fn($message, $context) => $message === 'First timer photo not removed' && $context['user_id'] === $firstTimer->id)
            ->once();
    });
});

describe('members delete', function () {
    it('lets only admins delete members', function () {
        $member = ftUser([], [RoleEnum::MEMBER->value]);

        ftAs($this, ftTokenFor(ftUser([], [RoleEnum::MEMBER->value])))
            ->postJson('/api/members/delete', ['memberIds' => [$member->id]])
            ->assertForbidden();
        ftAs($this, ftTokenFor(ftUser([], [RoleEnum::MEMBER->value])))
            ->deleteJson("/api/members/{$member->id}", ['memberIds' => [$member->id]])
            ->assertForbidden();
        expect(User::find($member->id))->not->toBeNull();

        ftAs($this, ftAdminToken())
            ->postJson('/api/members/delete', ['memberIds' => [$member->id]])
            ->assertOk();
        expect(User::find($member->id))->toBeNull();
    });
});

describe('deleting first timers safely', function () {
    beforeEach(function () {
        config(['filesystems.disks.cloudinary.cloud' => 'gccc-test']);
        $this->photoCalls = [];
        $this->mock(UploadService::class, function ($mock) {
            $mock->shouldReceive('delete')->andReturnUsing(function ($url) {
                $this->photoCalls[] = $url;
                return true;
            });
        });
    });

    it('only removes photos stored in the church’s own Cloudinary account', function () {
        $ours = 'https://res.cloudinary.com/gccc-test/image/upload/v1700000000/first-timers/face.jpg';
        $otherAccount = 'https://res.cloudinary.com/someone-else/image/upload/v1700000000/banner.jpg';
        $otherHost = 'https://evil.example/gccc-test/image/upload/v1700000000/banner.jpg';
        $a = ftFirstTimer('2025-09-01', [], ['avatar' => $ours, 'secondary_avatar' => $otherAccount]);
        $b = ftFirstTimer('2025-09-02', [], ['avatar' => $otherHost]);

        ftDelete($this, ftAdminToken(), [$a->id, $b->id])->assertOk();

        expect($this->photoCalls)->toBe([$ours]);
    });

    it('keeps a photo someone else still uses', function () {
        $shared = 'https://res.cloudinary.com/gccc-test/image/upload/v1700000000/events/banner.jpg';
        $firstTimer = ftFirstTimer('2025-09-01', [], ['avatar' => $shared]);
        ftUser(['secondary_avatar' => $shared], [RoleEnum::MEMBER->value]);

        ftDelete($this, ftAdminToken(), [$firstTimer->id])->assertOk();

        expect(User::find($firstTimer->id))->toBeNull();
        expect($this->photoCalls)->toBe([]);
    });

    it('leaves people and photos alone when the delete fails', function () {
        $photo = 'https://res.cloudinary.com/gccc-test/image/upload/v1700000000/first-timers/face.jpg';
        $first = ftFirstTimer('2025-09-01', [], ['avatar' => $photo]);
        $second = ftFirstTimer('2025-09-02');
        User::deleting(function (User $user) use ($second) {
            if ($user->id === $second->id) {
                throw new RuntimeException('Database went away');
            }
        });

        ftDelete($this, ftAdminToken(), [$first->id, $second->id])->assertServerError();

        expect(User::find($first->id))->not->toBeNull();
        expect(User::find($second->id))->not->toBeNull();
        expect($this->photoCalls)->toBe([]);
    });

    it('skips first timers who serve in a unit or have written follow-up notes', function () {
        $inUnit = ftFirstTimer('2025-09-01', [], ['first_name' => 'Unit', 'last_name' => 'Helper']);
        $inUnit->units()->attach(Unit::firstOrCreate(['name' => UnitEnum::FOLLOW_UP->value])->id);
        $noteWriter = ftFirstTimer('2025-09-02', [], ['first_name' => 'Note', 'last_name' => 'Writer']);
        $someoneElse = ftFirstTimer('2025-09-03');
        DB::table('followup_feedbacks')->insert([
            'user_id' => $someoneElse->id, 'created_by' => $noteWriter->id, 'type' => 'Others', 'note' => 'Called.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $plain = ftFirstTimer('2025-09-04');

        $response = ftDelete($this, ftAdminToken(), [$inUnit->id, $noteWriter->id, $plain->id])
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 1 first timer. Skipped 2 who serve in a unit or have written follow-up notes: Unit Helper, Note Writer.');

        expect(collect($response->json('data.skipped'))->pluck('reason')->all())->toBe(['in_use', 'in_use']);
        expect(User::find($inUnit->id))->not->toBeNull();
        expect(User::find($noteWriter->id))->not->toBeNull();
        expect(DB::table('followup_feedbacks')->where('created_by', $noteWriter->id)->count())->toBe(1);
        expect(User::find($plain->id))->toBeNull();
    });
});

/** Follow-up statuses exist in a real install with fixed ids (FollowUpStatus::NOT_CONTACTED_ID and friends). */
function ftSeedStatuses(): void
{
    foreach (range(1, 10) as $id) {
        DB::table('follow_up_statuses')->insertOrIgnore([
            'id' => $id, 'title' => "Status {$id}", 'color' => 'gray', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** A Follow Up Unit member who can be assigned first timers of their gender. */
function ftFollower(string $gender, array $attributes = []): User
{
    $user = ftUser(array_merge(['gender' => $gender], $attributes), [RoleEnum::MEMBER->value]);
    $user->units()->attach(Unit::firstOrCreate(['name' => UnitEnum::FOLLOW_UP->value])->id);

    return $user;
}

function ftQuickAdd(Tests\TestCase $test, string $token, array $overrides = []): Illuminate\Testing\TestResponse
{
    return ftAs($test, $token)->postJson('/api/first-timers/quick', array_merge([
        'first_name'    => 'Ada',
        'last_name'     => 'Obi',
        'phone_number'  => '08012345678',
        'gender'        => 'Female',
        'date_of_visit' => '2026-11-15',
    ], $overrides));
}

/** What the public welcome form sends today (createFormPayload in FirstTimerWelcomePage.jsx). */
function ftWelcomePayload(array $overrides = []): array
{
    return array_merge([
        'first_name'          => 'Bola',
        'last_name'           => 'Ade',
        'phone_number'        => '08099998888',
        'email'               => 'bola.ade@example.com',
        'gender'              => 'Female',
        'located_in_ibadan'   => true,
        'membership_interest' => 'Yes',
        'is_student'          => false,
        'born_again'          => 'Yes',
        'whatsapp_interest'   => true,
        'address'             => null,
        'date_of_birth'       => '2026-03-14',
        'date_of_visit'       => '2026-11-15T09:30:00.000Z',
        'occupation'          => 'Teacher',
        'service_experience'  => 'The worship.',
        'prayer_point'        => null,
        'invited_by'          => null,
        'status'              => 'active',
        'how_did_you_learn'   => 'Social Media',
    ], $overrides);
}

describe('adding a first timer', function () {
    beforeEach(function () {
        ftSeedStatuses();
        Http::preventStrayRequests();
        // The mail service answers OK unless a test sets $this->mailFails.
        $this->mailFails = false;
        Http::fake(fn() => $this->mailFails
            ? Http::response(['message' => 'Mail service down'], 500)
            : Http::response(['data' => [['code' => 'EM_104']]], 200));
        $this->travelTo('2026-11-20 12:00:00');
    });

    it('lets admins and the follow-up unit add a first timer, and nobody else', function () {
        ftQuickAdd($this, ftAdminToken())->assertCreated();
        ftQuickAdd($this, ftTokenFor(ftFollowUpMember()), ['phone_number' => '08011112222'])->assertCreated();
        ftQuickAdd($this, ftTokenFor(ftUser([], [RoleEnum::MEMBER->value])))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only admins and the follow-up unit can add first timers.');

        expect(User::firstTimers()->count())->toBe(2);
    });

    it('needs a name, phone, gender and a visit date that is not in the future', function () {
        $token = ftAdminToken();

        $response = ftAs($this, $token)->postJson('/api/first-timers/quick', [])->assertUnprocessable();
        expect(array_keys($response->json('errors')))->toEqualCanonicalizing(['first_name', 'last_name', 'phone_number', 'gender', 'date_of_visit']);

        ftQuickAdd($this, $token, ['date_of_visit' => '2026-11-21'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_of_visit.0', "The visit date can't be in the future.");
        ftQuickAdd($this, $token, ['gender' => 'Other'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.gender.0', 'Choose Male or Female.');
        expect(User::firstTimers()->count())->toBe(0);
    });

    it('refuses an email someone already uses', function () {
        ftUser(['email' => 'taken@example.com']);

        ftQuickAdd($this, ftAdminToken(), ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Someone already uses this email. Leave it blank or use another.');
    });

    it('stores a placeholder when no email is given, a different one each time', function () {
        $token = ftAdminToken();

        $first = ftQuickAdd($this, $token, ['email' => ''])->assertCreated()->json('data.email');
        $second = ftQuickAdd($this, $token, ['first_name' => 'Ade', 'phone_number' => '08022223333'])->assertCreated()->json('data.email');

        expect($first)->toMatch('/^no-email-[0-9a-f]{12}@gcccibadan\.invalid$/');
        expect($second)->toMatch('/^no-email-[0-9a-f]{12}@gcccibadan\.invalid$/');
        expect($first)->not->toBe($second);
    });

    it('answers with the new first timer as it was saved, active', function () {
        ftQuickAdd($this, ftAdminToken())
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.date_of_visit', '2026-11-15');
    });

    it('works out the week ending from the visit date', function () {
        $token = ftAdminToken();

        expect(ftQuickAdd($this, $token, ['date_of_visit' => '2026-11-15'])->json('data.week_ending'))->toBe('2026-11-15');
        expect(ftQuickAdd($this, $token, ['date_of_visit' => '2026-11-16', 'phone_number' => '08033334444'])->json('data.week_ending'))->toBe('2026-11-22');
    });

    it('assigns a follow-up person of the same gender and emails them', function () {
        ftFollower('Male', ['first_name' => 'Musa']);
        $follower = ftFollower('Female', ['first_name' => 'Femi', 'last_name' => 'Follow', 'email' => 'femi.follow@example.com']);

        $response = ftQuickAdd($this, ftAdminToken())
            ->assertCreated()
            ->assertJsonPath('message', 'Added Ada Obi. Femi Follow will follow up.');

        expect(User::find($response->json('data.id'))->followup_by_id)->toBe($follower->id);
        Http::assertSentCount(1);
        Http::assertSent(fn($request) => str_contains($request->body(), 'femi.follow@example.com'));
    });

    it('still saves when the email fails, or when nobody can be assigned', function () {
        Log::spy();
        ftFollower('Female');
        $this->mailFails = true;

        ftQuickAdd($this, ftAdminToken())->assertCreated();
        expect(User::firstTimers()->count())->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn($message) => $message === 'First timer assignment email not sent')->once();

        // Nobody of this gender in the follow-up unit: saved, and no email is attempted.
        ftQuickAdd($this, ftAdminToken(), ['gender' => 'Male', 'phone_number' => '08055556666'])
            ->assertCreated()
            ->assertJsonPath('message', 'Added Ada Obi. Nobody in the follow-up unit could be assigned yet.');
        expect(User::firstTimers()->count())->toBe(2);
        Http::assertSentCount(1);
    });
});

describe('public welcome form', function () {
    beforeEach(function () {
        ftSeedStatuses();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => [['code' => 'EM_104']]], 200)]);
    });

    it('still saves the welcome form exactly as it sends today', function () {
        ftFollower('Female');

        $this->postJson('/api/first-timers', ftWelcomePayload())->assertCreated();

        $firstTimer = User::firstTimers()->firstOrFail();
        expect($firstTimer->email)->toBe('bola.ade@example.com');
        expect($firstTimer->date_of_visit->toDateString())->toBe('2026-11-15');
        expect($firstTimer->week_ending->toDateString())->toBe('2026-11-15');
        expect($firstTimer->status)->toBe('active');
        expect($firstTimer->followup_by_id)->not->toBeNull();
    });

    it('ignores staff-only fields from the public', function () {
        $someone = ftUser();

        $this->postJson('/api/first-timers', ftWelcomePayload([
            'status'               => 'disabled',
            'avatar'               => 'https://res.cloudinary.com/demo/image/upload/v1/banner.jpg',
            'secondary_avatar'     => 'https://res.cloudinary.com/demo/image/upload/v1/other.jpg',
            'is_glory_team_member' => true,
            'follow_up_status_id'  => 7,
            'followup_by_id'       => $someone->id,
            'email_verified_at'    => '2026-01-01 00:00:00',
            'notes'                => 'Staff note',
            'pastorate_call'       => 'Done',
        ]))->assertCreated();

        $firstTimer = User::firstTimers()->firstOrFail();
        expect($firstTimer->status)->toBe('active');
        expect($firstTimer->avatar)->toBeNull();
        expect($firstTimer->secondary_avatar)->toBeNull();
        expect((bool) $firstTimer->is_glory_team_member)->toBeFalse();
        expect($firstTimer->email_verified_at)->toBeNull();
        expect($firstTimer->notes)->toBeNull();
        expect($firstTimer->pastorate_call)->toBeNull();
        expect($firstTimer->followup_by_id)->not->toBe($someone->id);
    });

    it('stores a placeholder when the public leaves email blank', function () {
        $this->postJson('/api/first-timers', ftWelcomePayload(['email' => null]))->assertCreated();

        expect(User::firstTimers()->firstOrFail()->email)->toMatch('/^no-email-[0-9a-f]{12}@gcccibadan\.invalid$/');
    });
});

describe('first timers without an email', function () {
    beforeEach(function () {
        ftSeedStatuses();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => [['code' => 'EM_104']]], 200)]);
    });

    it('won’t send a welcome email to the placeholder address', function () {
        $placeholder = 'no-email-0123456789ab@gcccibadan.invalid';
        $firstTimer = ftFirstTimer('2026-11-15', [], ['email' => $placeholder, 'follow_up_status_id' => 6]);

        ftAs($this, ftAdminToken())
            ->postJson("/api/first-timers/{$firstTimer->id}/welcome-email", ['email' => $placeholder, 'name' => 'Ada'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This first timer has no email address.');

        Http::assertNothingSent();
        expect($firstTimer->fresh()->follow_up_status_id)->toBe(6);
    });

    it('leaves placeholder addresses out of bulk mail', function () {
        $placeholder = ftFirstTimer('2026-11-15', [], ['email' => 'no-email-0123456789ab@gcccibadan.invalid']);
        $real = ftFirstTimer('2026-11-15', [], ['email' => 'real.person@example.com']);

        ftAs($this, ftAdminToken())
            ->postJson('/api/admin/mail/bulk', ['user_ids' => [$placeholder->id, $real->id], 'template_id' => 'tpl'])
            ->assertOk();

        Http::assertSent(fn($request) => str_contains($request->body(), 'real.person@example.com'));
        Http::assertNotSent(fn($request) => str_contains($request->body(), 'gcccibadan.invalid'));
    });
});

describe('public welcome form, safely', function () {
    beforeEach(function () {
        ftSeedStatuses();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => [['code' => 'EM_104']]], 200)]);
    });

    it('ignores anniversaries from the public', function () {
        $this->postJson('/api/first-timers', ftWelcomePayload(['anniversaries' => '-11-']))->assertCreated();

        expect(User::firstTimers()->firstOrFail()->anniversaries)->toBeNull();
    });

    it('does not tell the public who will follow up', function () {
        ftFollower('Female', ['email' => 'femi.follow@example.com']);

        $response = $this->postJson('/api/first-timers', ftWelcomePayload())->assertCreated();

        expect($response->json('data.assigned_to_member'))->toBeNull();
        expect($response->getContent())->not->toContain('femi.follow@example.com');
    });

    it('limits how often the public form can be sent', function () {
        foreach (range(1, 30) as $i) {
            $this->postJson('/api/first-timers', ftWelcomePayload([
                'email' => null, 'phone_number' => '0809' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
            ]))->assertCreated();
        }

        $this->postJson('/api/first-timers', ftWelcomePayload(['email' => null]))->assertStatus(429);
    });
});
