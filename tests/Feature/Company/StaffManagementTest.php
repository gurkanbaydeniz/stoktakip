<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Zincir Kahve A.Ş.']);
        $this->admin = User::create([
            'company_id' => $company->id,
            'name' => 'Patron',
            'email' => 'patron@zincir.com',
            'password' => 'Patron1234',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function staffToken(): string
    {
        $staff = User::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Barista Ayşe',
            'email' => 'ayse@zincir.com',
            'username' => 'ayse',
            'password' => 'Calisan123',
            'role' => User::ROLE_STAFF,
        ]);

        return $staff->createToken('test')->plainTextToken;
    }

    public function test_admin_calisan_olusturur(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/company/staff', [
                'name' => 'Barista Mehmet',
                'email' => 'mehmet@zincir.com',
                'username' => 'mehmet',
                'password' => 'IlkSifre1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_STAFF)
            ->assertJsonPath('data.company.name', 'Zincir Kahve A.Ş.');

        $staff = User::where('email', 'mehmet@zincir.com')->first();
        $this->assertSame($this->admin->company_id, $staff->company_id, 'Çalışan aynı şirkete bağlı olmalı.');
    }

    public function test_calisan_olusturulan_hesap_ile_giris_yapabilir(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/company/staff', [
                'name' => 'Kasiyer Zeynep',
                'email' => 'zeynep@zincir.com',
                'password' => 'IlkSifre1',
            ])->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'zeynep@zincir.com',
            'password' => 'IlkSifre1',
        ])->assertOk();
    }

    public function test_staff_calisan_olusturamaz_403(): void
    {
        $staffToken = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/company/staff', [
                'name' => 'Başka Biri',
                'email' => 'baska@zincir.com',
                'password' => 'Sifre123',
            ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'baska@zincir.com']);
    }

    public function test_staff_sirket_adini_degistiremez_403(): void
    {
        $staffToken = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->putJson('/api/v1/company', ['name' => 'Yeni Ad'])
            ->assertForbidden();

        $this->assertSame('Zincir Kahve A.Ş.', $this->admin->company->fresh()->name);
    }

    public function test_admin_sirket_adini_degistirir(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v1/company', ['name' => 'Zincir Kahve Holding'])
            ->assertOk()
            ->assertJsonPath('name', 'Zincir Kahve Holding');

        $this->assertSame('Zincir Kahve Holding', $this->admin->company->fresh()->name);
    }

    public function test_admin_calisanlari_listeler(): void
    {
        $this->staffToken();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/company/staff')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('Barista Ayşe', $response->json('data.0.name')); // alfabetik sıralı
    }

    public function test_admin_calisan_sifresini_sifirlar(): void
    {
        $staffToken = $this->staffToken();
        $staff = User::where('email', 'ayse@zincir.com')->first();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/company/staff/{$staff->id}", ['password' => 'YeniSifre123'])
            ->assertOk();

        // Eski token hâlâ geçerli ama yeni şifreyle giriş olur
        $this->postJson('/api/v1/auth/login', [
            'login' => 'ayse',
            'password' => 'YeniSifre123',
        ])->assertOk();

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_admin_kendini_silemez_403(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/company/staff/{$this->admin->id}")
            ->assertForbidden();

        $this->assertModelExists($this->admin);
    }

    public function test_admin_calisan_siler(): void
    {
        $this->staffToken();
        $staff = User::where('email', 'ayse@zincir.com')->first();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/company/staff/{$staff->id}")
            ->assertOk();

        $this->assertModelMissing($staff);
    }

    public function test_baska_sirketin_calisani_404_doner(): void
    {
        $otherCompany = Company::create(['name' => 'Rakip Şirket']);
        $other = User::create([
            'company_id' => $otherCompany->id,
            'name' => 'Rakip Çalışan',
            'email' => 'rakip@rakip.com',
            'password' => 'Rakip1234',
            'role' => User::ROLE_STAFF,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/company/staff/{$other->id}")
            ->assertNotFound();
    }
}
