<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\AbsenteeResource;
use App\Http\Resources\UserResource;
use App\Services\UploadService;
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
    protected UploadService $uploadService;
    public function __construct(UserService $userService, UploadService $uploadService)
    {
        $this->userService = $userService;
        $this->uploadService = $uploadService;
    }
    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();
            if (!empty($validated['avatar'])) {
                if ($user->avatar != null) {
                    $this->uploadService->delete($user->avatar);
                }
                $validated['avatar'] = $this->uploadService->upload(
                    $validated['avatar'],
                    $request->folder ?? 'users'
                );
            }
            $user->update($validated);
            $user->fresh();
            $user->loadFullProfile();
            $data = ['user' => new UserResource($user)];
            return $this->successResponse($data, 'Profile updated successfully', Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (Throwable $th) {
            return $this->errorResponse(
                $th->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    ///////////////////////   LEADERS  ////////////////////////////////////
    public function getAssignedAbsentees(Request $request): JsonResponse
    {
        $user = $request->user();
        $absentees = $this->userService
            ->getAssignedAbsenteesForLeader($user->id);
        return $this->successResponse(AbsenteeResource::collection($absentees), '');
    }

}
