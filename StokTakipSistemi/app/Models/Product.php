<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'barcode', 'unit', 'critical_stock_level', 'notes'])]
class Product extends Model
{
    protected $attributes = [
        'unit' => 'adet',
        'critical_stock_level' => 0,
    ];

    protected function casts(): array
    {
        return [
            'critical_stock_level' => 'decimal:3',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Güncel stok: ürünün tüm hareketlerinin (işaretli) toplamı.
     */
    public function stockQuantity(): float
    {
        return (float) $this->stockMovements()->sum('quantity');
    }

    public function isBelowCriticalStock(): bool
    {
        return $this->stockQuantity() <= (float) $this->critical_stock_level;
    }

    /**
     * Liste sorgularına tek sorguda stok toplamı ekler (N+1 önler).
     */
    public function scopeWithStockQuantity(Builder $query): Builder
    {
        return $query
            ->select('products.*')
            ->selectRaw('COALESCE(SUM(sm.quantity), 0) as stock_quantity')
            ->leftJoin('stock_movements as sm', 'sm.product_id', '=', 'products.id')
            ->groupBy('products.id');
    }

    /**
     * Kritik seviyenin altına düşen ürünler.
     */
    public function scopeBelowCriticalStock(Builder $query): Builder
    {
        return $query
            ->select('products.*')
            ->selectRaw('COALESCE(SUM(stock_movements.quantity), 0) as stock_quantity')
            ->leftJoin('stock_movements', 'stock_movements.product_id', '=', 'products.id')
            ->groupBy('products.id')
            ->havingRaw('COALESCE(SUM(stock_movements.quantity), 0) <= products.critical_stock_level');
    }
}
