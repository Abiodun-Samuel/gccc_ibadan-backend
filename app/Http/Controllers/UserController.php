<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AbsenteeResource;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Update user profile
     */
    public function update(UpdateUserRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();
            $folder = $request->input('folder', 'users');

            $updatedUser = $this->userService->updateProfile($user, $validated, $folder);

            $data = ['user' => new UserResource($updatedUser)];

            return $this->successResponse(
                $data,
                'Profile updated successfully',
                Response::HTTP_OK
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Exception | Throwable $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get assigned absentees for leader
     */
    public function getAssignedAbsentees(Request $request): JsonResponse
    {
        $user = $request->user();
        $absentees = $this->userService->getAssignedAbsenteesForLeader($user->id);

        return $this->successResponse(
            AbsenteeResource::collection($absentees),
            'Assigned absentees retrieved successfully'
        );
    }

    /**
     * Get assigned members for leader
     */
    public function getAssignedMembers(Request $request): JsonResponse
    {
        $user = $request->user();
        $members = $this->userService->getAssignedMembers($user);

        return $this->successResponse(
            $members,
            'Assigned members retrieved successfully'
        );
    }
}
