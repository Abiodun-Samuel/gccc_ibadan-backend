<?php
namespace App\Http\Controllers;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\UserRolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRolePermissionService $rolePermissionService
    ) {
    }

    /**
     * login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        try {
            $result = $this->authService->authenticate($credentials);

            return $this->successResponse(
                $result,
                'Logged in successfully',
                Response::HTTP_OK
            );
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'user_not_found' => 'User does not exist, please contact the admin.',
                'invalid_password' => 'Password is incorrect, please reset your password.',
                default => 'An error occurred during authentication',
            };

            return $this->errorResponse(
                $message,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }


    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->register($validated);

        return $this->successResponse(
            $result,
            'Registration successful',
            Response::HTTP_CREATED
        );
    }

    /**
     * Get authenticated user details
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadFullProfile();

        return $this->successResponse(
            ['user' => UserResource::make($user)],
            'User details retrieved successfully',
            Response::HTTP_OK
        );
    }

    /**
     * Logout user and revoke current token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            'Logged out successfully',
            Response::HTTP_NO_CONTENT
        );
    }

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        try {
            $this->authService->sendResetLink($email);

            return $this->successResponse(
                null,
                'Password reset link sent to your email',
                Response::HTTP_OK
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->authService->resetPassword($data);

            return $this->successResponse(
                null,
                'Password has been reset successfully',
                Response::HTTP_OK
            );
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'invalid_token' => 'Invalid or expired reset token',
                'user_not_found' => 'User does not exist',
                default => 'Unable to reset password',
            };

            return $this->errorResponse(
                $message,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
