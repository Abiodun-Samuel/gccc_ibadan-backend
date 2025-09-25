<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Requests\BulkCreateMembersRequest;
use App\Http\Requests\BulkUpdateMembersRequest;
use App\Http\Resources\UserResource;
use App\Services\MemberService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService
    ) {
    }

    /**
     * Display a listing of members
     */
    public function index(): JsonResponse
    {
        try {
            $members = $this->memberService->getAllMembers();

            return $this->successResponse(UserResource::collection($members), 'Members retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(null, $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created member
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        try {
            $member = $this->memberService->createMember($request->validated());
            return $this->successResponse(new UserResource($member), 'Member created successfully', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Failed to create member', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified member
     */
    public function show(int $id): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($id);
            return $this->successResponse(new UserResource($member), 'Member retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(null, 'Member not found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Member not found', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified member
     */
    public function update(UpdateMemberRequest $request, int $id): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($id);
            $updatedMember = $this->memberService->updateMember($member, $request->validated());

            return $this->successResponse(new UserResource($updatedMember), 'Member updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(null, 'Member not found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Failed to update member', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified member
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $member = $this->memberService->findMember($id);
            $this->memberService->deleteMember($member);

            return $this->successResponse(null, 'Member deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(null, 'Member not found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Failed to delete member', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk create members
     */
    public function bulkCreate(BulkCreateMembersRequest $request): JsonResponse
    {
        try {
            $results = $this->memberService->bulkCreateMembers($request->validated('members'));

            $createdCount = count($results['created']);
            $failedCount = count($results['failed']);
            $totalProcessed = $results['total_processed'];

            $message = "Bulk create completed. Created: {$createdCount}, Failed: {$failedCount}, Total processed: {$totalProcessed}";

            return $this->successResponse($results, $message);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Failed to bulk create members', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkUpdate(BulkUpdateMembersRequest $request): JsonResponse
    {
        try {
            $results = $this->memberService->bulkUpdateMembersByEmail($request->validated('members'));

            $updatedCount = count($results['updated']);
            $notFoundCount = count($results['not_found']);
            $failedCount = count($results['failed']);
            $totalProcessed = $results['total_processed'];

            $message = "Bulk update completed. Updated: {$updatedCount}, Not found: {$notFoundCount}, Failed: {$failedCount}, Total processed: {$totalProcessed}";

            return $this->successResponse($results, $message);
        } catch (\Exception $e) {
            return $this->errorResponse(null, 'Failed to bulk update members', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
