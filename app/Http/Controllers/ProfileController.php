<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Update authenticated user's profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }
        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        // Update user avatar path
        $user->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar' => $user->avatar,
                'avatar_url' => Storage::disk('public')->url($user->avatar)
            ]
        ]);
    }
}
