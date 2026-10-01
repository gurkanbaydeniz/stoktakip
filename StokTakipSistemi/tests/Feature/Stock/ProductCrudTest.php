<?php

namespace Tests\Feature\Stock;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
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

    public function test_admin_urun_olusturur(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/products', [
                'name' => 'Çekirdek Kahve 1kg',
                'barcode' => '8690000000012',
                'unit' => 'pk',
                'critical_stock_level' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Çekirdek Kahve 1kg')
            ->assertJsonPath('data.unit', 'pk')
            ->assertJsonPath('data.critical_stock_level', 5)
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.is_below_critical_stock', true); // stok 0 <= kritik 5
    }

    public function test_staff_urun_olusturamaz_403(): void
    {
        $this->withSanctumToken($this->staffToken)
            ->postJson('/api/v1/products', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_staff_urunleri_listeler_ve_goruntuler(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/products', ['name' => 'Süt 1L', 'unit' => 'lt']);

        $this->withSanctumToken($this->staffToken)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Süt 1L');

        $id = $this->staff->company->products()->first()->id;
        $this->withSanctumToken($this->staffToken)
            ->getJson("/api/v1/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 0);
    }

    public function test_barkod_ayni_sirkette_benzersizdir(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/products', ['name' => 'A', 'barcode' => 'BARKOD1'])
            ->assertCreated();

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/products', ['name' => 'B', 'barcode' => 'BARKOD1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcode');
    }

    public function test_ayni_barkod_baska_sirkette_olabilir(): void
    {
        $otherCompany = Company::create(['name' => 'Rakip']);
        User::create([
            'company_id' => $otherCompany->id,
            'name' => 'Rakip Admin',
            'email' => 'rakip@test.com',
            'password' => 'Rakip1234',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->withSanctumToken($this->adminToken)
            ->postJson('/api/v1/products', ['name' => 'A', 'barcode' => 'ORTAK-BARKOD'])
            ->assertCreated();

        $otherToken = User::where('email', 'rakip@test.com')->first()->createToken('t')->plainTextToken;
        $this->withSanctumToken($otherToken)
            ->postJson('/api/v1/products', ['name' => 'B', 'barcode' => 'ORTAK-BARKOD'])
            ->assertCreated();
    }

    public function test_admin_urun_gunceller(): void
    {
        $product = $this->admin->company->products()->create([
            'name' => 'Eski Ad',
            'critical_stock_level' => 2,
        ]);

        $this->withSanctumToken($this->adminToken)
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Yeni Ad',
                'critical_stock_level' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Yeni Ad')
            ->assertJsonPath('data.critical_stock_level', 10);
    }

    public function test_hareketsiz_urun_silinir_hareketli_silinemez(): void
    {
        $empty = $this->admin->company->products()->create(['name' => 'Boş Ürün']);
        $this->withSanctumToken($this->adminToken)
            ->deleteJson("/api/v1/products/{$empty->id}")
            ->assertOk();

        $used = $this->admin->company->products()->create(['name' => 'Kullanılan']);
        $used->stockMovements()->create([
            'company_id' => $this->admin->company_id,
            'user_id' => $this->admin->id,
            'type' => 'in',
            'quantity' => 3,
        ]);

        $this->withSanctumToken($this->adminToken)
            ->deleteJson("/api/v1/products/{$used->id}")
            ->assertConflict();
    }

    public function test_baska_sirketin_urunu_404(): void
    {
        $otherCompany = Company::create(['name' => 'Rakip']);
        $otherProduct = $otherCompany->products()->create(['name' => 'Rakip Ürünü']);

        $this->withSanctumToken($this->adminToken)
            ->getJson("/api/v1/products/{$otherProduct->id}")
            ->assertNotFound();
    }
}
