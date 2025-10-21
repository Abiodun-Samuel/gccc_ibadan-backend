<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserRolePermissionService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Str;

class AuthService
{
    private const TOKEN_EXPIRY_MINUTES = 60;
    public $rolePermissionService;
    public $mailService;
    public function __construct(UserRolePermissionService $rolePermissionService, MailService $mailService)
    {
        $this->rolePermissionService = $rolePermissionService;
        $this->mailService = $mailService;
    }
    /**
     * Authenticate user with credentials
     */
    public function authenticate(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw new RuntimeException('user_not_found');
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            throw new RuntimeException('invalid_password');
        }

        $user->loadFullProfile();
        $token = $user->createToken($user->email)->plainTextToken;

        return [
            'token' => $token,
            'user' => UserResource::make($user),
        ];
    }
    private function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['phone_number']);
        $user = User::create($data);
        $role = $data['role'] ?? RoleEnum::MEMBER->value;
        $this->rolePermissionService->assignRoleAndSyncPermissions($user, [$role]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => UserResource::make($user),
        ];
    }

    public function sendResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new RuntimeException('User does not exist');
        }

        // Delete any existing tokens for this email
        $this->deleteExistingTokens($email);

        // Generate new token
        $token = $this->generateToken();

        // Store token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        $recipients = [
            [
                'name' => $user->first_name,
                'email' => $user->email
            ]
        ];
        $resetUrl = env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        $this->mailService->sendResetPasswordEmail($resetUrl, $recipients);
    }
    public function resetPassword(array $data): void
    {
        $tokenData = $this->getTokenData($data['email']);

        if (!$tokenData) {
            throw new RuntimeException('invalid_token');
        }

        // Verify token hasn't expired
        if ($this->isTokenExpired($tokenData->created_at)) {
            $this->deleteExistingTokens($data['email']);
            throw new RuntimeException('invalid_token');
        }

        // Verify token matches
        if (!Hash::check($data['token'], $tokenData->token)) {
            throw new RuntimeException('invalid_token');
        }

        // Find and update user password
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            throw new RuntimeException('user_not_found');
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        // Delete used token
        $this->deleteExistingTokens($data['email']);

        // Revoke all existing tokens for security
        $user->tokens()->delete();
    }

    private function generateToken(): string
    {
        return Str::random(64);
    }

    private function deleteExistingTokens(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    private function getTokenData(string $email): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();
    }

    private function isTokenExpired(string $createdAt): bool
    {
        $expiryTime = Carbon::parse($createdAt)
            ->addMinutes(self::TOKEN_EXPIRY_MINUTES);

        return Carbon::now()->isAfter($expiryTime);
    }
}
