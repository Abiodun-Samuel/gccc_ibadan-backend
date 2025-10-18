<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Request;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public $memberService;
    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

    public function index(): JsonResponse
    {
        try {
            $members = $this->memberService->getAllMembers();
            return $this->successResponse(UserResource::collection($members), 'Members retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getMembersByRole(Request $request, string $role)
    {
        try {
            $members = $this->memberService->getUsersByRole($role);
            return $this->successResponse(UserResource::collection($members), ucfirst($role) . "s retrieved successfully", Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        try {
            $member = $this->memberService->createMember($request->validated());
            return $this->successResponse(new UserResource($member), 'Member created successfully', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create member', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(User $member): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($member);
            return $this->successResponse(new UserResource($member), 'Member retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Member not found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse('Member not found', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateMemberRequest $request, User $member): JsonResponse
    {
        try {
            $updatedMember = $this->memberService->updateMember($member, $request->validated());
            return $this->successResponse(new UserResource($updatedMember), 'Member updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse("Failed to update member:" . ${$e->getMessage()}, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(User $member): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($member);
            $this->memberService->deleteMember($member);

            return $this->successResponse(null, 'Member deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Member not found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete member', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function unassign(User $member)
    {
        $member->update([
            'assigned_to_user_id' => null,
            'assigned_at' => null,
        ]);

        return response()->json(['message' => 'User unassigned successfully.']);
    }
}
