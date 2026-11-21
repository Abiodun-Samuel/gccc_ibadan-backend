<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignMembersRequest;
use App\Http\Requests\DeleteMembersRequest;
use App\Http\Requests\StoreBulkMemberRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\MemberDeletionService;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public $memberService;
    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }
    private function buildSuccessMessage(array $result): string
    {
        $total = $result['total'];
        $successful = $result['successful_count'];
        $failed = $result['failed_count'];

        if ($failed === 0) {
            return $successful === 1
                ? 'Member created successfully'
                : "{$successful} members created successfully";
        }

        return "{$successful} of {$total} members created successfully. {$failed} failed.";
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_of_birth',
                'birth_month',
                'community'
            ]);
            $members = $this->memberService->getAllMembers($filters);
            return $this->successResponse(UserResource::collection($members), 'Members retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getAllUsers(): JsonResponse
    {
        try {
            $users = $this->memberService->getAllUsers();
            return $this->successResponse(UserResource::collection($users), 'users retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getMembersByRole(Request $request, string $role)
    {
        $filters = $request->only([
            'date_of_birth',
            'birth_month',
            'community',
        ]);
        try {
            $members = $this->memberService->getUsersByRole($role, $filters);
            return $this->successResponse(UserResource::collection($members), ucfirst($role) . "s retrieved successfully", Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(StoreBulkMemberRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $result = $this->memberService->createMembers($request->validated()['members']);
            DB::commit();
            $message = $this->buildSuccessMessage($result);

            return $this->successResponse([
                'created' => UserResource::collection($result['successful']),
                'failed' => $result['failed'],
                'summary' => [
                    'total' => $result['total'],
                    'successful' => $result['successful_count'],
                    'failed' => $result['failed_count']
                ]
            ], $message, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to create members:' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(User $member): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($member);
            return $this->successResponse(new UserResource($member), 'Member retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateUserRequest $request, User $member): JsonResponse
    {
        try {
            $updatedMember = $this->memberService->updateMember($member, $request->validated());
            return $this->successResponse(new UserResource($updatedMember), 'Member updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse("Failed to update member:" . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Admins: permanently delete several people. Never the admin asking, admins or pastors, or
     * anyone whose follow-up work or unit leadership ties them to other people (see MemberDeletionService).
     */
    public function destroy(DeleteMembersRequest $request, MemberDeletionService $deletion): JsonResponse
    {
        $ids = array_map('intval', $request->validated('memberIds'));
        $result = $deletion->deleteMany($ids, $request->user());

        return $this->successResponse($result, $deletion->message($result));
    }

    public function assignMembers(AssignMembersRequest $request): JsonResponse
    {
        try {
            $result = $this->memberService->assignMembersToFollowupLeaders(
                $request->input('member_ids'),
                $request->input('followup_leader_ids')
            );
            return $this->successResponse($result, 'Members have been assigned successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Assignment failed:' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function updateGloryTeamMembers(): JsonResponse
    {
        try {
            $result = $this->memberService->updateGloryTeamMembers();
            return $this->successResponse($result, 'Glory team members have been updated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed:' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
