<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bearer token ile istek atar. Aynı test içinde kullanıcı değiştirmeden önce
     * auth guard önbelleğini sıfırlar (gerçek HTTP'te her istek zaten sıfırdan
     * kimliklendirir; yalnızca test ortamı davranışıdır).
     */
    protected function withSanctumToken(string $token): static
    {
        if ($this->app) {
            $this->app->make('auth')->forgetGuards();
        }

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Misafir (token'sız) istek atar: kalıcı header'ları ve guard önbelleğini
     * temizler (withHeader/withSanctumToken test boyunca kalıcıdır).
     */
    protected function asGuest(): static
    {
        if ($this->app) {
            $this->app->make('auth')->forgetGuards();
        }

        return $this->flushHeaders();
    }
}
