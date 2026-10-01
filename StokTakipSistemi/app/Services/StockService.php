<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok domaine kuralları: hareketler değişmezdir, her stok değişikliği
 * stock_movements'a kayıt düşer. Mevcut stok = hareketlerin toplamı.
 */
class StockService
{
    /**
     * Stok girişi: SKT'li parti oluşturur ve ilk "in" hareketini yazar.
     *
     * @param  array<string, mixed>  $data
     */
    public function stockIn(User $actor, Product $product, array $data): Batch
    {
        return DB::transaction(function () use ($actor, $product, $data): Batch {
            $batch = Batch::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'batch_code' => $data['batch_code'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
            ]);

            StockMovement::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'user_id' => $actor->id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $data['quantity'], // giriş: pozitif delta
                'waybill_number' => $data['waybill_number'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            return $batch;
        });
    }

    /**
     * Stok çıkışı: FIFO — SKT'si en yakın partiden başlayarak tüketir.
     * Belirli bir parti verilirse yalnızca ondan düşer.
     *
     * @param  float  $quantity  Pozitif miktar.
     * @return StockMovement[] Oluşturulan "out" hareketleri.
     */
    public function stockOut(User $actor, Product $product, float $quantity, ?Batch $specificBatch = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($actor, $product, $quantity, $specificBatch, $note): array {
            $batches = ($specificBatch !== null ? collect([$specificBatch]) : $this->fifoBatches($product))
                ->mapWithKeys(fn (Batch $batch) => [
                    $batch->id => (float) $batch->stockMovements()
                        ->lockForUpdate()
                        ->sum('quantity'),
                ]);

            $available = $batches->sum();
            if ($available + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Yetersiz stok. Girilen: {$quantity}, mevcut: ".round($available, 3).' '.$product->unit,
                ]);
            }

            $movements = [];
            $needed = $quantity;

            foreach ($batches as $batchId => $remaining) {
                if ($needed <= 0.0001 || $remaining <= 0.0001) {
                    continue;
                }

                $take = min($remaining, $needed);

                $movements[] = StockMovement::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'user_id' => $actor->id,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => -$take, // çıkış: negatif delta
                    'note' => $note,
                ]);

                $needed -= $take;
            }

            return $movements;
        });
    }

    /**
     * Sayım düzeltmesi: işaretli delta ile yeni bir "adjustment" hareketi yazar.
     */
    public function adjust(User $actor, Product $product, float $signedQuantity, ?int $batchId = null, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($actor, $product, $signedQuantity, $batchId, $note): StockMovement {
            $currentStock = (float) $product->stockMovements()->lockForUpdate()->sum('quantity');

            if ($currentStock + $signedQuantity < -0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => 'Düzeltme stoğu negatife düşürür. Mevcut stok: '.round($currentStock, 3),
                ]);
            }

            return StockMovement::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'batch_id' => $batchId,
                'user_id' => $actor->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => $signedQuantity,
                'note' => $note,
            ]);
        });
    }

    /**
     * FIFO sırası: SKT'si en yakın önce (SKT'siz olanlar en son).
     */
    private function fifoBatches(Product $product): iterable
    {
        return Batch::query()
            ->where('product_id', $product->id)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC, received_at ASC, id ASC')
            ->lockForUpdate()
            ->get();
    }
}
