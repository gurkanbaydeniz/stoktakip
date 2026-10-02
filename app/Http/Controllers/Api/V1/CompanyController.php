<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanyUpdateRequest;
use App\Http\Requests\Company\StaffStoreRequest;
use App\Http\Requests\Company\StaffUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    /**
     * İşletme sahibi şirket/dükkan adını günceller (yalnızca admin).
     */
    public function update(CompanyUpdateRequest $request): JsonResponse
    {
        $company = $request->user()->company;
        $company->update(['name' => $request->string('name')->trim()]);

        return response()->json(['name' => $company->name]);
    }

    public function staffIndex(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $request->user()->company->users()->orderBy('name')->get()
        );
    }

    /**
     * Admin, kendi şirketine çalışan (staff) hesabı açar.
     */
    public function staffStore(StaffStoreRequest $request): JsonResponse
    {
        $staff = User::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->string('name')->trim(),
            'email' => $request->string('email')->lower()->trim(),
            'username' => $request->filled('username') ? $request->string('username')->trim() : null,
            'password' => $request->string('password'),
            'role' => User::ROLE_STAFF,
        ]);

        return (new UserResource($staff->load('company')))
            ->response()
            ->setStatusCode(201);
    }

    public function staffUpdate(StaffUpdateRequest $request, User $user): UserResource
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);

        $user->update($request->only(['name', 'username']));

        if ($request->filled('password')) {
            $user->update(['password' => $request->string('password')]);
        }

        return new UserResource($user->load('company'));
    }

    /**
     * Admin, çalışan hesabını siler. Kendini silemez.
     */
    public function staffDestroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);
        abort_if($user->id === $request->user()->id, 403, 'Kendi hesabınızı silemezsiniz.');

        $user->delete();

        return response()->json(['message' => 'Çalışan hesabı silindi.']);
    }
}
