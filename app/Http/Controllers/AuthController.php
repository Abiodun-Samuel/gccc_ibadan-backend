<?php
namespace App\Http\Controllers;
use App\Enums\RoleEnum;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserRolePermissionService;
use DB;
use Illuminate\Http\Request;
use Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserRolePermissionService $service
    ) {
    }
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',  // phone or email
            'password' => 'required|string',
        ]);

        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';
        $user = User::where($loginField, $credentials['username'])->first();
        if (!$user || !\Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials', 422);
        }
        $user->loadFullProfile();
        $token = $user->createToken('auth_token')->plainTextToken;
        $data = ['token' => $token, 'user' => new UserResource($user)];
        return $this->successResponse($data, 'Logged in successfully', 200);
    }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone_number' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'community' => 'nullable|string',
            'worker' => 'nullable|string',
            'unit' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['phone_number']);

        $user = User::create($validated);
        $this->service->assignRoleAndSyncPermissions($user, [RoleEnum::MEMBER->value]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $data = ['token' => $token, 'user' => new UserResource($user)];
        return $this->successResponse($data, 'Logged in successfully', 201);
    }
    public function me(Request $request)
    {
        $user = $request->user()->loadFullProfile();
        ;
        $data = new UserResource($user);
        return $this->successResponse(['user' => $data], 'user details', 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(NULL, 'Logged out successfully', 204);
    }
}
