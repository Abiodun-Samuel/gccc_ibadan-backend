<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    public $unitService;
    public function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
    }
    public function index()
    {
        $units = Unit::with(['leader', 'assistantLeader', 'members'])->withCount('members')->latest()->get();
        return $this->successResponse(UnitResource::collection($units), Response::HTTP_OK);
    }

    public function store(UpdateUnitRequest $request)
    {
        $validated = $request->validated();

        $unit = $this->unitService->createUnit($validated);

        return $this->successResponse(
            new UnitResource($unit),
            'Unit created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $validated = $request->validated();
        $this->unitService->updateUnit($unit, $validated);

        return $this->successResponse(
            $unit->fresh(['leader', 'assistantLeader', 'members']),
            'Unit updated successfully.',
            Response::HTTP_OK
        );
    }
    public function destroy(Unit $unit)
    {
        $this->unitService->deleteUnit($unit);
        return $this->successResponse([], 'Unit deleted successfully.', Response::HTTP_OK);
    }
}
