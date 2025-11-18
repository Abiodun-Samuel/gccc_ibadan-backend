<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkMemberRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
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

    private function buildBulkDeleteMessage(array $result): string
    {
        $deleted = $result['deleted_count'];
        $failed = $result['failed_count'];
        $total = $deleted + $failed;
        if ($failed === 0) {
            return $deleted === 1
                ? 'Member deleted successfully'
                : "{$deleted} members deleted successfully";
        }
        if ($deleted === 0) {
            return "Failed to delete all {$total} members";
        }
        return "{$deleted} of {$total} members deleted successfully, {$failed} failed";
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
    public function getMembersByRole(Request $request, string $role)
    {
        try {
            $members = $this->memberService->getUsersByRole($role);
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

    public function destroy(Request $request): JsonResponse
    {
        try {
            $ids = $request->validate([
                'memberIds' => ['required', 'array', 'min:1', 'max:100'],
                'memberIds.*' => ['required', 'integer', 'exists:users,id'],
            ]);

            $result = $this->memberService->deleteMembers($ids['memberIds']);

            $message = $this->buildBulkDeleteMessage($result);

            return $this->successResponse($result, $message);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete members: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
