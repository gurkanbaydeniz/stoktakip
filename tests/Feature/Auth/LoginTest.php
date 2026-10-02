<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $companyId = Company::create(['name' => 'Test Şirket'])->id;

        return User::create([
            'company_id' => $companyId,
            'name' => 'Test Kullanıcı',
            'email' => 'test@stok.com',
            'username' => 'testci',
            'password' => 'Gizli1234',
            'role' => User::ROLE_STAFF,
        ]);
    }

    public function test_email_ile_giris_yapilir(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'test@stok.com',
            'password' => 'Gizli1234',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user'])
            ->assertJsonPath('user.email', 'test@stok.com');
    }

    public function test_kullanici_adi_ile_giris_yapilir(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'testci',
            'password' => 'Gizli1234',
        ])->assertOk()->assertJsonPath('user.username', 'testci');
    }

    public function test_hatali_sifre_422_doner(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'test@stok.com',
            'password' => 'yanlis-sifre',
        ])->assertUnprocessable();
    }

    public function test_olmayan_kullanici_422_doner(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'yok@boyle.com',
            'password' => 'herhangibir',
        ])->assertUnprocessable();
    }

    public function test_token_ile_me_ucusu_calisir(): void
    {
        $user = $this->user();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'test@stok.com')
            ->assertJsonPath('data.role', User::ROLE_STAFF);
    }

    public function test_tokensuz_me_ucusu_401_doner(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_tokeni_gecersiz_kilar(): void
    {
        $user = $this->user();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Test ortamında auth guard istekler arasında önbelleklenir; gerçek
        // HTTP'de her istek sıfırdan kimliklendirir. Guard'ı sıfırlayıp doğruluyoruz.
        $this->app->make('auth')->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
