<?php

use App\Enums\RoleEnum;
use App\Models\Unit;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (RoleEnum::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->mock(UploadService::class);
});

// Helpers are prefixed "mb" so they never clash with other test files' global helpers.

function mbUser(array $roles = [RoleEnum::MEMBER->value], array $attributes = []): User
{
    $user = User::forceCreate(array_merge([
        'first_name'   => 'Test',
        'last_name'    => 'Member',
        'email'        => uniqid('mb', true) . '@example.com',
        'phone_number' => '080' . random_int(10000000, 99999999),
        'password'     => Hash::make('password'),
    ], $attributes));

    foreach ($roles as $role) {
        $user->assignRole($role);
    }

    return $user;
}

/** Requests as the token's owner; the guard is reset so a different token is really used. */
function mbAs(Tests\TestCase $test, User $user): Tests\TestCase
{
    auth()->forgetGuards();

    return $test->withToken($user->createToken('test')->plainTextToken);
}

function mbDelete(Tests\TestCase $test, User $as, array $ids): Illuminate\Testing\TestResponse
{
    return mbAs($test, $as)->postJson('/api/members/delete', ['memberIds' => $ids]);
}

function mbService(): int
{
    return DB::table('services')->insertGetId(['name' => 'Sunday Service', 'created_at' => now(), 'updated_at' => now()]);
}

describe('deleting members', function () {
    it('is for admins only', function () {
        $member = mbUser();

        mbDelete($this, mbUser(), [$member->id])->assertForbidden();

        expect(User::find($member->id))->not->toBeNull();
    });

    it('deletes members and everything about them, but not their unit', function () {
        $admin = mbUser([RoleEnum::ADMIN->value]);
        $member = mbUser();
        $unit = Unit::create(['name' => 'Choir']);
        $member->units()->attach($unit->id);
        DB::table('attendances')->insert([
            'user_id' => $member->id, 'service_id' => mbService(), 'attendance_date' => '2026-11-15',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $member->createToken('phone');

        $response = mbDelete($this, $admin, [$member->id])
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 1 member.')
            ->assertJsonPath('data.skipped', []);

        expect(collect($response->json('data.deleted'))->pluck('id')->all())->toBe([$member->id]);
        expect(User::find($member->id))->toBeNull();
        expect(DB::table('unit_user')->where('user_id', $member->id)->count())->toBe(0);
        expect(DB::table('attendances')->where('user_id', $member->id)->count())->toBe(0);
        expect(DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $member->id)->count())->toBe(0);
        expect(DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $member->id)->count())->toBe(0);
        expect(Unit::find($unit->id))->not->toBeNull();
    });

    it('never deletes the admin doing it, admins or pastors', function () {
        $admin = mbUser([RoleEnum::ADMIN->value, RoleEnum::MEMBER->value]);
        $otherAdmin = mbUser([RoleEnum::ADMIN->value], ['first_name' => 'Other', 'last_name' => 'Admin']);
        $pastor = mbUser([RoleEnum::PASTOR->value, RoleEnum::MEMBER->value], ['first_name' => 'Pastor', 'last_name' => 'Ade']);

        $response = mbDelete($this, $admin, [$admin->id, $otherAdmin->id, $pastor->id])
            ->assertOk()
            ->assertJsonPath('message', 'No members were deleted. Skipped your own account. Skipped 2 admins or pastors: Other Admin, Pastor Ade.');

        expect(collect($response->json('data.skipped'))->pluck('reason')->all())->toBe(['self', 'protected', 'protected']);
        expect(User::whereIn('id', [$admin->id, $otherAdmin->id, $pastor->id])->count())->toBe(3);
    });

    it('skips members whose work is tied to other people', function () {
        $admin = mbUser([RoleEnum::ADMIN->value]);
        $someone = mbUser();
        $noteWriter = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Note', 'last_name' => 'Writer']);
        DB::table('followup_feedbacks')->insert([
            'user_id' => $someone->id, 'created_by' => $noteWriter->id, 'type' => 'Others', 'note' => 'Called.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $absenteeLeader = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Absentee', 'last_name' => 'Leader']);
        DB::table('absentee_assignments')->insert([
            'leader_id' => $absenteeLeader->id, 'user_id' => $someone->id, 'service_id' => mbService(),
            'attendance_date' => '2026-11-15', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $followsUp = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Follows', 'last_name' => 'Up']);
        mbUser([RoleEnum::FIRST_TIMER->value], ['followup_by_id' => $followsUp->id]);
        $leader = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Unit', 'last_name' => 'Leader']);
        $assistant = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Unit', 'last_name' => 'Assistant']);
        $assistant2 = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Unit', 'last_name' => 'Second']);
        Unit::create(['name' => 'Choir', 'leader_id' => $leader->id, 'assistant_leader_id' => $assistant->id, 'assistant_leader_id_2' => $assistant2->id]);
        $ids = [$noteWriter->id, $absenteeLeader->id, $followsUp->id, $leader->id, $assistant->id, $assistant2->id];

        $response = mbDelete($this, $admin, $ids)
            ->assertOk()
            ->assertJsonPath('message', 'No members were deleted. Skipped 6 who still have follow-up work or lead a unit: Note Writer, Absentee Leader, Follows Up, Unit Leader, Unit Assistant, Unit Second.');

        expect(collect($response->json('data.skipped'))->pluck('reason')->unique()->all())->toBe(['in_use']);
        expect(User::whereIn('id', $ids)->count())->toBe(6);
        expect(DB::table('followup_feedbacks')->where('created_by', $noteWriter->id)->count())->toBe(1);
        expect(DB::table('absentee_assignments')->where('leader_id', $absenteeLeader->id)->count())->toBe(1);
    });

    it('says "who still has" for one', function () {
        $admin = mbUser([RoleEnum::ADMIN->value]);
        $leader = mbUser([RoleEnum::MEMBER->value], ['first_name' => 'Unit', 'last_name' => 'Leader']);
        Unit::create(['name' => 'Choir', 'leader_id' => $leader->id]);
        $plain = mbUser();

        mbDelete($this, $admin, [$leader->id, $plain->id])
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 1 member. Skipped 1 who still has follow-up work or leads a unit: Unit Leader.');
    });

    it('reports ids it cannot find and limits a delete to 100', function () {
        $admin = mbUser([RoleEnum::ADMIN->value]);
        $member = mbUser();

        mbDelete($this, $admin, [999999, $member->id])
            ->assertOk()
            ->assertJsonPath('message', 'Deleted 1 member. 1 could not be found.')
            ->assertJsonPath('data.skipped.0.reason', 'not_found');

        mbDelete($this, $admin, range(1, 101))
            ->assertUnprocessable()
            ->assertJsonPath('errors.memberIds.0', 'You can delete up to 100 members at a time.');
        mbDelete($this, $admin, [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.memberIds.0', 'Choose at least one member to delete.');
    });
});

describe('members list', function () {
    it('lists everyone with their roles, so admins and pastors can be told apart', function () {
        $admin = mbUser([RoleEnum::ADMIN->value]);
        $pastor = mbUser([RoleEnum::PASTOR->value]);

        $rows = collect(mbAs($this, $admin)->getJson('/api/admin/members/role/all')->assertOk()->json('data'))->keyBy('id');

        expect($rows[$pastor->id]['roles'])->toContain('pastor');
        expect($rows[$admin->id]['roles'])->toContain('admin');
    });
});
