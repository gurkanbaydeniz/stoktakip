<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = isset($this->stock_quantity)
            ? (float) $this->stock_quantity
            : $this->stockQuantity();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'critical_stock_level' => (float) $this->critical_stock_level,
            'stock_quantity' => $stock,
            'is_below_critical_stock' => $stock <= (float) $this->critical_stock_level,
            'notes' => $this->notes,
            'batches_count' => $this->whenCounted('batches'),
            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
