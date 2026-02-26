<?php

namespace App\Services;

use App\Models\RedeemableItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RedeemableItemService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}
    /*--------------------------------------------------------------
    | CRUD — Admin
    --------------------------------------------------------------*/

    public function store(array $data): RedeemableItem
    {
        if (!empty($data['image'])) {
            $data['image'] = $this->uploadService->upload($data['image'], 'redeemable_items');
        }

        return RedeemableItem::create($data);
    }

    public function update(RedeemableItem $item, array $data): RedeemableItem
    {
        if (isset($data['image'])) {
            if ($this->isBase64($data['image'])) {
                if ($item->image) {
                    $this->uploadService->delete($item->image);
                }

                $data['image'] = $this->uploadService->upload($data['image'], 'redeemable_items');
            } else {
                unset($data['image']);
            }
        }
        $item->update($data);

        return $item->fresh();
    }

    private function isBase64(string $value): bool
    {
        if (str_starts_with($value, 'data:')) {
            return true;
        }
        $decoded = base64_decode($value, strict: true);
        return $decoded !== false && base64_encode($decoded) === $value;
    }

    public function destroy(RedeemableItem $item): void
    {
        $item->delete();
    }

    /*--------------------------------------------------------------
    | Redeem — User spends points, stock decreases, user recorded
    --------------------------------------------------------------*/

    /**
     * @throws ValidationException
     */
    public function redeem(User $user, RedeemableItem $item): void
    {
        DB::transaction(function () use ($user, $item) {
            $item = RedeemableItem::lockForUpdate()->findOrFail($item->id);

            if (!$item->is_active) {
                throw ValidationException::withMessages([
                    'item' => 'This item is no longer available.',
                ]);
            }

            if ($item->stock !== null && $item->stock < 1) {
                throw ValidationException::withMessages([
                    'item' => 'This item is out of stock.',
                ]);
            }

            if ($user->reward_points < $item->points_required) {
                throw ValidationException::withMessages([
                    'points' => "Insufficient points. You need {$item->points_required} points but have {$user->reward_points}.",
                ]);
            }

            // Deduct user points
            User::where('id', $user->id)->decrement('reward_points', $item->points_required);

            // Reduce stock if not unlimited
            if ($item->stock !== null) {
                $item->decrement('stock');
            }

            // Increment analytics counter
            $item->increment('total_redeemed');

            // Record this user as a redeemer — attach to pivot
            $item->redeemers()->attach($user->id, [
                'points_spent' => $item->points_required,
                'redeemed_at'  => now(),
            ]);
        });
    }
}
