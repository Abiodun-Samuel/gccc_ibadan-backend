<?php
namespace App\Http\Controllers;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {

        $credentials = $request->validate([
            'username' => 'required|string',  // phone or email
            'password' => 'required|string',
        ]);

        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($loginField, $credentials['username'])->first();
        if (!$user || !\Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse(null, 'Invalid credentials', 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $data = ['token' => $token, 'user' => new UserResource($user)];
        return $this->successResponse($data, 'Logged in successfully', 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $data = new UserResource($user);
        return $this->successResponse(['user' => $data], 'user details', 200);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
