<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * İşletme sahibi kaydı: şirketi oluşturur ve kullanıcıyı admin yapar.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $company = Company::create(['name' => $request->string('company_name')->trim()]);

            return User::create([
                'company_id' => $company->id,
                'name' => $request->string('name')->trim(),
                'email' => $request->string('email')->lower()->trim(),
                'username' => $request->filled('username') ? $request->string('username')->trim() : null,
                'password' => $request->string('password'),
                'role' => User::ROLE_ADMIN,
            ]);
        });

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('company')),
        ], 201);
    }

    /**
     * E-posta veya kullanıcı adı ile giriş.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::findByLogin($request->string('login')->trim());

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return response()->json([
                'message' => 'Giriş bilgileri hatalı.',
                'errors' => ['login' => ['E-posta/kullanıcı adı veya şifre hatalı.']],
            ], 422);
        }

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('company')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('company'));
    }
}
