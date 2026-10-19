<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller' => new UserSummaryResource($this->whenLoaded('user')),
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_path !== null
                ? Storage::disk('public')->url($this->image_path)
                : null,
            // Integers in kopecks - never floats.
            'starting_price' => $this->starting_price,
            'min_increment' => $this->min_increment,
            'current_price' => $this->current_price,
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status->value,
            'winner' => new UserSummaryResource($this->whenLoaded('winner')),
            // Populated by withCount()/loadCount() - an attribute, not a relation.
            'bids_count' => $this->when(isset($this->resource->bids_count), $this->resource->bids_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
