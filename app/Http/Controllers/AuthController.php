<?php
namespace App\Http\Controllers;
use App\Enums\RoleEnum;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\UserRolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRolePermissionService $rolePermissionService
    ) {
    }

    /**
     * Authenticate user and generate token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authService->authenticate($credentials);

        if (!$result) {
            return $this->errorResponse('Invalid credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->successResponse(
            $result,
            'Logged in successfully',
            Response::HTTP_OK
        );
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
}
