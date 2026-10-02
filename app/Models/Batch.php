<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['company_id', 'product_id', 'batch_code', 'expiry_date', 'quantity', 'unit_cost', 'supplier_name', 'received_at'])]
class Batch extends Model
{
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Partiden kalan miktar: giriş miktarı + partiye bağlı tüm hareketler.
     */
    public function remainingQuantity(): float
    {
        return (float) $this->stockMovements()->sum('quantity');
    }

    /**
     * Liste sorgularına tek sorguda kalan miktarı ekler (N+1 önler).
     */
    public function scopeWithRemainingQuantity(Builder $query): Builder
    {
        return $query
            ->select('batches.*')
            ->selectRaw('COALESCE(SUM(sm.quantity), 0) as remaining_quantity')
            ->leftJoin('stock_movements as sm', 'sm.batch_id', '=', 'batches.id')
            ->groupBy('batches.id');
    }

    /** Tek model için kalan miktarı attribute olarak yükler. */
    public function loadRemainingQuantity(): static
    {
        $this->attributes['remaining_quantity'] = $this->remainingQuantity();

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * Geçerlilik süresi belirtilen gün içinde dolacak partiler.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::today(), Carbon::today()->addDays($days)]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')->where('expiry_date', '<', Carbon::today());
    }
}
