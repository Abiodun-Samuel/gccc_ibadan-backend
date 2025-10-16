<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFollowupFeedbackRequest;
use App\Http\Resources\FollowupFeedbackResource;
use App\Models\FirstTimer;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FollowupFeedbackController extends Controller
{
    public $followUpService;
    public function __construct(FollowUpService $followUpService)
    {
        $this->followUpService = $followUpService;
    }

    public function index()
    {
    }

    public function store(StoreFollowupFeedbackRequest $request): JsonResponse
    {
        $followUp = $this->followUpService->createFollowUp($request->validated());
        return $this->successResponse(
            new FollowupFeedbackResource($followUp),
            'Followup feedback has been saved successfully',
            Response::HTTP_OK
        );
    }
    public function getFollowUpsByFirstTimer(FirstTimer $firstTimer): JsonResponse
    {
        $followUps = $this->followUpService->getFollowUpsByFirstTimer($firstTimer);
        return $this->successResponse(
            FollowupFeedbackResource::collection($followUps),
            '',
            Response::HTTP_OK
        );
    }
    public function show(string $id)
    {
    }

    public function update(Request $request, string $id)
    {
    }


    public function destroy(string $id)
    {
    }
}
