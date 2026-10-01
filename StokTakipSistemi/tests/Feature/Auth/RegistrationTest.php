<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_isletme_sahibi_kayit_olur_sirket_olusur_ve_token_alir(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Kahve Dünyası Şube 3',
            'name' => 'Gürkan Baydeniz',
            'email' => 'gurkan@kahvedunyasi.com',
            'username' => 'gurkan',
            'password' => 'Gizli1234',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'company' => ['id', 'name']]])
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonPath('user.company.name', 'Kahve Dünyası Şube 3');

        $this->assertNotEmpty($response->json('token'));

        $user = User::where('email', 'gurkan@kahvedunyasi.com')->first();
        $this->assertTrue(Hash::check('Gizli1234', $user->password), 'Şifre hash\'lenmiş kaydedilmeli.');
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertNotNull($user->company_id);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_gecersiz_kayit_istegi_422_doner(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => '',
            'name' => 'Test',
            'email' => 'gecersiz-eposta',
            'password' => '123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['company_name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_zaten_kayitli_ise_422_doner(): void
    {
        User::create([
            'name' => 'Mevcut',
            'email' => 'mevcut@test.com',
            'password' => 'Password1',
            'company_id' => Company::create(['name' => 'X'])->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Yeni Şirket',
            'name' => 'Yeni',
            'email' => 'mevcut@test.com',
            'password' => 'Password1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
