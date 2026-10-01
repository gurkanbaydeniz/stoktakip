<?php

namespace Tests\Feature\Stock;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private string $adminToken;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Kahve Zinciri']);
        $this->admin = User::create([
            'company_id' => $company->id,
            'name' => 'Patron',
            'email' => 'patron@test.com',
            'password' => 'Patron1234',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->staff = User::create([
            'company_id' => $company->id,
            'name' => 'Barista',
            'email' => 'barista@test.com',
            'password' => 'Barista123',
            'role' => User::ROLE_STAFF,
        ]);

        $this->adminToken = $this->admin->createToken('t')->plainTextToken;
        $this->staffToken = $this->staff->createToken('t')->plainTextToken;
    }

    private function productId(string $name): int
    {
        return $this->admin->company->products()->create(['name' => $name])->id;
    }

    private function stockIn(int $productId, float $quantity, ?string $expiryDate, array $extra = []): object
    {
        return $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/batches', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'expiry_date' => $expiryDate,
                ...$extra,
            ]);
    }

    private function stockQuantity(int $productId): float
    {
        return (float) DB::table('stock_movements')->where('product_id', $productId)->sum('quantity');
    }

    public function test_admin_parti_girisi_yapar_stok_ve_hareket_olusur(): void
    {
        $productId = $this->productId('Süt 1L');

        $this->stockIn($productId, 24, '2027-01-15', ['batch_code' => 'LOT-42', 'waybill_number' => 'IRS-001'])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 24)
            ->assertJsonPath('data.remaining_quantity', 24)
            ->assertJsonPath('data.expiry_date', '2027-01-15');

        $this->assertSame(24.0, $this->stockQuantity($productId));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'type' => 'in',
            'quantity' => 24,
        ]);
    }

    public function test_staff_parti_girisi_yapamaz_403(): void
    {
        $productId = $this->productId('Süt 1L');

        $this->withSanctumToken($this->staffToken)
            ->postJson('/api/v1/batches', ['product_id' => $productId, 'quantity' => 10])
            ->assertForbidden();
    }

    public function test_staff_stok_cikisi_yapabilir(): void
    {
        $productId = $this->productId('Süt 1L');
        $this->stockIn($productId, 10, '2027-01-15');

        $this->withSanctumToken($this->staffToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.type', 'out')
            ->assertJsonPath('data.0.quantity', -3);

        $this->assertSame(7.0, $this->stockQuantity($productId));
    }

    public function test_fifo_skt_si_yakin_partiden_once_duser(): void
    {
        $productId = $this->productId('Çekirdek Kahve');
        $this->stockIn($productId, 5, '2026-11-01'); // önce tüketilmeli
        $this->stockIn($productId, 5, '2027-06-01');

        // 7 birim çıkışı: 5 eski partiden + 2 yeni partiden
        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => 7,
            ])
            ->assertCreated();

        $batches = $this->admin->company->batches()->where('product_id', $productId)->orderBy('expiry_date')->get();
        $this->assertSame(0.0, $batches[0]->remainingQuantity(), 'SKT yakın parti tükenmeli.');
        $this->assertSame(3.0, $batches[1]->remainingQuantity());
        $this->assertSame(3.0, $this->stockQuantity($productId));

        $outMovements = DB::table('stock_movements')->where('product_id', $productId)->where('type', 'out')->get();
        $this->assertCount(2, $outMovements, 'FIFO iki partiye bölmeli.');
    }

    public function test_yetersiz_stok_422_ve_stok_degismez(): void
    {
        $productId = $this->productId('Süt 1L');
        $this->stockIn($productId, 4, '2027-01-15');

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(4.0, $this->stockQuantity($productId));
    }

    public function test_belirli_partiden_cikis_yalnizca_o_partiyi_duser(): void
    {
        $productId = $this->productId('Çekirdek Kahve');
        $this->stockIn($productId, 5, '2026-11-01');
        $this->stockIn($productId, 5, '2027-06-01');

        $newerBatchId = $this->admin->company->batches()->where('product_id', $productId)->orderByDesc('expiry_date')->first()->id;

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => 2,
                'batch_id' => $newerBatchId,
            ])
            ->assertCreated();

        $batches = $this->admin->company->batches()->where('product_id', $productId)->orderBy('expiry_date')->get();
        $this->assertSame(5.0, $batches[0]->remainingQuantity(), 'FIFO partisine dokunulmamalı.');
        $this->assertSame(3.0, $batches[1]->remainingQuantity());
    }

    public function test_partinin_kalanindan_fazla_cikis_422(): void
    {
        $productId = $this->productId('Çekirdek Kahve');
        $this->stockIn($productId, 5, '2026-11-01');
        $batchId = $this->admin->company->batches()->where('product_id', $productId)->first()->id;

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => 7,
                'batch_id' => $batchId,
            ])
            ->assertUnprocessable();
    }

    public function test_duzeltme_admin_ile_mumkun_staff_403(): void
    {
        $productId = $this->productId('Süt 1L');
        $this->stockIn($productId, 10, '2027-01-15');

        $this->withSanctumToken($this->staffToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'adjustment',
                'quantity' => -2,
            ])
            ->assertForbidden();

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'adjustment',
                'quantity' => -2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment');

        $this->assertSame(8.0, $this->stockQuantity($productId));
    }

    public function test_duzeltme_stogu_negatif_yapamaz(): void
    {
        $productId = $this->productId('Süt 1L');
        $this->stockIn($productId, 3, '2027-01-15');

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $productId,
                'type' => 'adjustment',
                'quantity' => -5,
            ])
            ->assertUnprocessable();
    }

    public function test_hareket_listesi_filtrelenir(): void
    {
        $milkId = $this->productId('Süt 1L');
        $coffeeId = $this->productId('Kahve');
        $this->stockIn($milkId, 10, '2027-01-15');
        $this->stockIn($coffeeId, 6, null);

        $this->withSanctumToken($this->staffToken)
            ->getJson("/api/v1/stock-movements?product_id={$milkId}&type=in")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.name', 'Süt 1L');
    }
}
