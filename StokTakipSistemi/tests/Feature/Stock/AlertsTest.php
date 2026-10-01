<?php

namespace Tests\Feature\Stock;

use App\Models\Company;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Kahve Zinciri']);
        $admin = User::create([
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
        $this->staffToken = $this->staff->createToken('t')->plainTextToken;

        // Kritik stok ürünü: kritik 5, stok 3
        $low = $company->products()->create([
            'name' => 'Süt 1L',
            'critical_stock_level' => 5,
        ]);
        $low->stockMovements()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'type' => 'in',
            'quantity' => 3,
        ]);

        // Sağlıklı ürün: kritik 2, stok 10 (uyarıda OLMAMALI)
        $ok = $company->products()->create([
            'name' => 'Peynir',
            'critical_stock_level' => 2,
        ]);
        $ok->stockMovements()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        // Partiler: 10 gün içinde SKT, 40 gün sonra SKT, geçmiş SKT
        $this->batch($company, $admin, 'Yaklaşan Süt', 4, Carbon::today()->addDays(10));
        $this->batch($company, $admin, 'Uzak Süt', 4, Carbon::today()->addDays(40));
        $this->batch($company, $admin, 'Geçmiş Süt', 2, Carbon::today()->subDay());
    }

    private function batch(Company $company, User $admin, string $productName, float $quantity, Carbon $expiry): void
    {
        $product = $company->products()->create(['name' => $productName]);
        $batch = $company->batches()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'expiry_date' => $expiry->toDateString(),
        ]);
        StockMovement::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'user_id' => $admin->id,
            'type' => 'in',
            'quantity' => $quantity,
        ]);
    }

    public function test_uyarilar_kritik_stok_ve_skt_dogrulur(): void
    {
        $response = $this->withSanctumToken($this->staffToken)
            ->getJson('/api/v1/alerts')
            ->assertOk();

        // Yaklaşan SKT (30 gün): 10 gün sonraki dahil, 40 gün sonraki hariç
        $expiring = collect($response->json('data.expiring_batches'));
        $this->assertSame(['Yaklaşan Süt'], $expiring->pluck('product.name')->all());
        $this->assertSame(10, $expiring->first()['days_until_expiry']);

        // Geçmiş SKT
        $expired = collect($response->json('data.expired_batches'));
        $this->assertSame(['Geçmiş Süt'], $expired->pluck('product.name')->all());

        // Kritik stok: "Süt 1L" var (3 <= 5), "Peynir" yok
        $low = collect($response->json('data.low_stock_products'));
        $this->assertSame(['Süt 1L'], $low->pluck('name')->all());
        $this->assertSame(3.0, (float) $low->first()['stock_quantity']);
    }

    public function test_days_parametresi_filtreyi_degistirir(): void
    {
        $response = $this->withSanctumToken($this->staffToken)
            ->getJson('/api/v1/alerts?days=60')
            ->assertOk();

        $names = collect($response->json('data.expiring_batches'))->pluck('product.name')->sort()->values()->all();
        $this->assertSame(['Uzak Süt', 'Yaklaşan Süt'], $names);
    }

    public function test_gecersiz_days_parametresi_422(): void
    {
        $this->withSanctumToken($this->staffToken)
            ->getJson('/api/v1/alerts?days=5000')
            ->assertUnprocessable();
    }
}
