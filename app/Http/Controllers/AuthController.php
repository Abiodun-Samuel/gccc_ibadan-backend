<?php
namespace App\Http\Controllers;
use App\Http\Resources\UserResource;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {

        $credentials = $request->validate([
            'username' => 'required|string',  // phone or email
            'password' => 'required|string',
        ]);

        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';
        $user = User::where($loginField, $credentials['username'])->first();
        if (!$user || !\Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse(null, 'Invalid credentials', 401);
        }
        $user->load('units');
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
            'worker' => 'nullable|in:Yes,No',
            'unit' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['phone_number']);

        $user = User::create($validated);
        // Assign default role
        $user->assignRole('member');

        $token = $user->createToken('auth_token')->plainTextToken;
        $data = ['token' => $token, 'user' => new UserResource($user)];
        return $this->successResponse($data, 'Logged in successfully', 201);
    }
    public function bulkRegister(Request $request)
    {
        $validated = $request->validate([
            'users' => 'required|array|min:1',
            'users.*.first_name' => 'required|string',
            'users.*.last_name' => 'required|string',
            'users.*.email' => 'required|email|unique:users,email',
            'users.*.phone_number' => 'required|string|unique:users,phone_number',
        ]);

        $results = [];
        DB::beginTransaction();
        try {
            foreach ($validated['users'] as $userData) {
                $user = User::create([
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'phone_number' => $userData['phone_number'],
                    'email' => $userData['email'],
                    'password' => bcrypt($userData['phone_number']),
                ]);

                $user->assignRole('member');

                $results[] = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                    'status' => 'registered',
                ];
            }

            DB::commit();
            return response()->json([
                'message' => 'Users registered successfully',
                'results' => $results,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error registering users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function me(Request $request)
    {
        $user = $request->user();
        $data = new UserResource($user);
        return $this->successResponse(['user' => $data], 'user details', 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(NULL, 'Logged out successfully', 204);
    }
}
