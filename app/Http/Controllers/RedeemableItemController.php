<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRedeemableItemRequest;
use App\Http\Requests\UpdateRedeemableItemRequest;
use App\Http\Resources\RedeemableItemResource;
use App\Models\RedeemableItem;
use App\Services\RedeemableItemService;
use App\Services\UploadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RedeemableItemController extends Controller
{
    public function __construct(
        private readonly RedeemableItemService $service,
    ) {}

    /*--------------------------------------------------------------
    | Public — any authenticated user
    --------------------------------------------------------------*/

    /**
     * GET /api/redeemable-items
     * Active items — no redeemers loaded (not needed by regular users)
     */
    public function index(): JsonResponse
    {
        $items = RedeemableItem::where('is_active', true)
            ->orderBy('points_required')
            ->get();

        return $this->successResponse(
            data: RedeemableItemResource::collection($items),
        );
    }

    /**
     * POST /api/redeemable-items/{redeemableItem}/redeem
     */
    public function redeem(Request $request, RedeemableItem $redeemableItem): JsonResponse
    {
        $user = $request->user();
        $this->service->redeem($user, $redeemableItem);

        $user->refresh();
        $redeemableItem->refresh();

        return $this->successResponse(
            data: [
                'item'             => new RedeemableItemResource($redeemableItem),
                'points_spent'     => $redeemableItem->points_required,
                'remaining_points' => $user->reward_points,
            ],
            message: "You have successfully redeemed \"{$redeemableItem->title}\".",
        );
    }

    /*--------------------------------------------------------------
    | Admin — redeemers loaded on all admin views
    --------------------------------------------------------------*/

    /**
     * GET /api/admin/redeemable-items
     * All items with redeemers list.
     */
    public function adminIndex(): JsonResponse
    {
        $items = RedeemableItem::with('redeemers')
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse(
            data: RedeemableItemResource::collection($items),
        );
    }

    /**
     * POST /api/admin/redeemable-items
     */
    public function store(StoreRedeemableItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        $item = $this->service->store(data: $data);

        return $this->successResponse(
            data: new RedeemableItemResource($item),
            message: 'Item created successfully.',
            code: Response::HTTP_CREATED,
        );
    }

    /**
     * PUT /api/admin/redeemable-items/{redeemableItem}
     */
    public function update(UpdateRedeemableItemRequest $request, RedeemableItem $redeemableItem): JsonResponse
    {
        $data = $request->validated();

        $item = $this->service->update(
            item: $redeemableItem,
            data: $data,
        );

        return $this->successResponse(
            data: new RedeemableItemResource($item),
            message: 'Item updated successfully.',
        );
    }

    /**
     * DELETE /api/admin/redeemable-items/{redeemableItem}
     */
    public function destroy(RedeemableItem $redeemableItem): JsonResponse
    {
        $this->service->destroy($redeemableItem);

        return $this->successResponse(
            data: null,
            message: 'Item deleted.',
        );
    }
}
