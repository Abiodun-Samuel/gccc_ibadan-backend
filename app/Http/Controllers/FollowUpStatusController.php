<?php

namespace App\Http\Controllers;

use App\Models\FollowUpStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FollowUpStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $statuses = FollowUpStatus::all();
        return $this->successResponse($statuses, 'Follow up statuses retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255|unique:follow_up_statuses,title',
            'color' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $status = FollowUpStatus::create($validatedData);

        return $this->successResponse($status, 'Follow up status created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FollowUpStatus  $followUpStatus
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($int)
    {
        $followUpStatus = FollowUpStatus::findOrFail($int);
        return $this->successResponse($followUpStatus, 'Follow up status retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FollowUpStatus  $followUpStatus
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, FollowUpStatus $followUpStatus)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255|unique:follow_up_statuses,title,' . $followUpStatus->id,
            'color' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $followUpStatus->update($validatedData);
        return $this->successResponse($followUpStatus, 'Follow up status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FollowUpStatus  $followUpStatus
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(FollowUpStatus $followUpStatus)
    {
        $followUpStatus->delete();

        return $this->successResponse(null, 'Follow up status deleted successfully.', Response::HTTP_NO_CONTENT);
    }
}
