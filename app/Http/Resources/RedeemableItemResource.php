<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RedeemableItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'subtitle'        => $this->subtitle,
            'description'     => $this->description,
            'image'           => $this->image,
            'points_required' => $this->points_required,
            'stock'           => $this->stock,
            'stock_label'     => $this->stock_label,
            'is_active'       => $this->is_active,
            'is_available'    => $this->is_available,
            'category'        => $this->category,
            'total_redeemed'  => $this->total_redeemed,

            'redeemers'       => $this->whenLoaded(
                'redeemers',
                fn() =>
                $this->redeemers->map(fn($user) => [
                    'id'          => $user->id,
                    'name'        => $user->first_name . ' ' . $user->last_name,
                    'avatar'      => $user->avatar,
                    'points_spent' => $user->pivot->points_spent,
                    'redeemed_at' => $user->pivot->redeemed_at,
                ])
            ),

            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
