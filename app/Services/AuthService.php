<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserRolePermissionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly UserRolePermissionService $rolePermissionService
    ) {
    }

    /**
     * Authenticate user with credentials
     */
    public function authenticate(array $credentials): ?array
    {
        $loginField = $this->determineLoginField($credentials['username']);

        $user = User::where($loginField, $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        $user->loadFullProfile();
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => UserResource::make($user),
        ];
    }


    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['phone_number']);
        $data['full_name'] = trim("{$data['first_name']} {$data['last_name']}");
        $data['initials'] = $this->generateInitials($data['first_name'], $data['last_name']);

        $user = User::create($data);
        $role = $data['role'] ?? RoleEnum::MEMBER->value;
        $this->rolePermissionService->assignRoleAndSyncPermissions($user, [$role]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => UserResource::make($user),
        ];
    }

    private function generateInitials(string $firstName, string $lastName): string
    {
        $firstInitial = Str::upper(Str::substr($firstName, 0, 1));
        $lastInitial = Str::upper(Str::substr($lastName, 0, 1));

        return $firstInitial . $lastInitial;
    }

    /**
     * Determine if login field is email or phone
     */
    private function determineLoginField(string $username): string
    {
        return filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';
    }
}
