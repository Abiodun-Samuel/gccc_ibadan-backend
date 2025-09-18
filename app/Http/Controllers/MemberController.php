<?php

namespace App\Http\Controllers;

use App\DTOs\MemberData;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private MemberService $memberService)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $members = $this->memberService->listMembers(
            perPage: $request->get('per_page', 15),
            search: $request->get('search'),
            sortBy: $request->get('sort_by', 'created_at'),
            sortDir: $request->get('sort_dir', 'desc')
        );

        return $this->paginatedResponse(
            UserResource::collection($members),
            'Members fetched successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberRequest $request)
    {
        $dto = MemberData::fromArray($request->validated());
        $member = $this->memberService->createMember($dto)->load('units');

        return $this->successResponse(
            new UserResource($member),
            'Member created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(User $member)
    {
        $member->load('units');
        return $this->successResponse(
            new UserResource($member),
            'Member details retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberRequest $request, User $member)
    {
        $dto = MemberData::fromArray($request->validated());
        $updatedMember = $this->memberService->updateMember($member, $dto)->load('units');

        return $this->successResponse(
            new UserResource($updatedMember),
            'Member updated successfully'
        );
    }

    /**
     * Bulk create or update members by email.
     * Each record must contain an email.
     */
    public function bulkUpsert(Request $request)
    {
        $validated = $request->validate([
            'members' => 'required|array|min:1',
            'members.*.email' => 'required|email',
            'members.*.first_name' => 'required|string|max:255',
            'members.*.last_name' => 'required|string|max:255',
            'members.*.phone_number' => 'nullable',
            'members.*.gender' => 'nullable|string',
            'members.*.address' => 'nullable|string|max:500',
            'members.*.community' => 'nullable|string|max:255',
            'members.*.worker' => 'nullable|string',
            'members.*.status' => 'nullable|string',
            'members.*.date_of_birth' => 'nullable|string',
            'members.*.date_of_visit' => 'nullable|string',
            'members.*.country' => 'nullable|string|max:255',
            'members.*.city_or_state' => 'nullable|string|max:255',
            'members.*.facebook' => 'nullable|url',
            'members.*.instagram' => 'nullable|url',
            'members.*.linkedin' => 'nullable|url',
            'members.*.twitter' => 'nullable|url',
            'members.*.password' => 'nullable|string|min:8',
            // 'members.*.units' => 'nullable|array',
            // 'members.*.units.*.id' => 'required_with:members.*.units|exists:units,id',
            // 'members.*.units.*.is_leader' => 'boolean'
        ]);

        $result = $this->memberService->bulkUpsertMembers($validated['members']);

        return $this->successResponse($result, 'Bulk member operation completed successfully');
        //         {
//   "members": [
//     {
//       "first_name": "John",
//       "last_name": "Doe",
//       "email": "john@example.com",
//       "phone_number": "+2348000000001",
//       "gender": "male",
//       "community": "Downtown Parish",
//       "status": "active",
//       "units": [
//         { "id": 1, "is_leader": false },
//         { "id": 2, "is_leader": true }
//       ]
//     },
//     {
//       "first_name": "Jane",
//       "last_name": "Smith",
//       "email": "jane@example.com",
//       "phone_number": "+2348000000002",
//       "gender": "female",
//       "community": "Central Parish",
//       "status": "active",
//       "units": [
//         { "id": 3, "is_leader": false }
//       ]
//     }
//   ]
// }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $member)
    {
        $this->memberService->deleteMember($member);
        return $this->successResponse(null, 'Member deleted successfully');
    }

}
