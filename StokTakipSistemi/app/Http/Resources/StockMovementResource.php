<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_id' => $this->product_id,
            'batch_id' => $this->batch_id,
            'user' => $this->when($this->relationLoaded('user') && $this->user !== null, new UserResource($this->user)),
            'waybill_number' => $this->waybill_number,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
