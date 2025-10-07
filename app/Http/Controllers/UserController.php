<?php

namespace App\Http\Controllers;

use App\Http\Resources\AbsenteeResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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
