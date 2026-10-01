<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Batch */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remaining = isset($this->remaining_quantity)
            ? (float) $this->remaining_quantity
            : $this->remainingQuantity();

        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_id' => $this->product_id,
            'batch_code' => $this->batch_code,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $this->expiry_date !== null
                ? (int) round(now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false))
                : null,
            'is_expired' => $this->isExpired(),
            'quantity' => (float) $this->quantity,
            'remaining_quantity' => $remaining,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'supplier_name' => $this->supplier_name,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
