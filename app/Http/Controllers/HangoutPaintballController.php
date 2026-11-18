<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovePaintballPlayerRequest;
use App\Http\Requests\SavePaintballGroupRequest;
use App\Models\HangoutPaintballGroup;
use App\Services\PaintballGroupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin side of the hangout's paintball groups: move players between
 * groups, add, rename and remove groups, or rearrange everyone evenly.
 * Each reply has every group, as the registrations list shows them.
 */
class HangoutPaintballController extends Controller
{
    public function __construct(private readonly PaintballGroupService $paintball) {}

    /** POST /api/admin/hangout/paintball/groups */
    public function store(SavePaintballGroupRequest $request): JsonResponse
    {
        $group = $this->paintball->createGroup($request->validated());

        return $this->groups("{$group->name} added. New players join it until it has enough.", Response::HTTP_CREATED);
    }

    /** PATCH /api/admin/hangout/paintball/groups/{group} */
    public function update(SavePaintballGroupRequest $request, HangoutPaintballGroup $group): JsonResponse
    {
        $group->update($request->validated());

        return $this->groups("Saved {$group->name}.");
    }

    /** DELETE /api/admin/hangout/paintball/groups/{group}: only an empty group. */
    public function destroy(HangoutPaintballGroup $group): JsonResponse
    {
        $this->paintball->deleteGroup($group);

        return $this->groups("Removed {$group->name}.");
    }

    /** POST /api/admin/hangout/paintball/groups/rearrange */
    public function rearrange(): JsonResponse
    {
        $this->paintball->rearrange();

        return $this->groups('Rearranged everyone into even groups.');
    }

    /** PATCH /api/admin/hangout/paintball/players/{kind}/{id}: kind is adult or child. */
    public function move(MovePaintballPlayerRequest $request, string $kind, int $id): JsonResponse
    {
        $group = HangoutPaintballGroup::findOrFail($request->validated('group_id'));
        $name = $this->paintball->move($kind, $id, $group);

        return $this->groups("Moved {$name} to {$group->name}.");
    }

    private function groups(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse([
            'min_group_size' => $this->paintball->minGroupSize(),
            'groups'         => $this->paintball->forAdmin(),
        ], $message, $status);
    }
}
