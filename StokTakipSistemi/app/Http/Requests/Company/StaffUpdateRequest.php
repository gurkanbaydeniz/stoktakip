<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StaffUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'min:3', 'max:50', 'alpha_dash',
                "unique:users,username,{$userId}"],
            'password' => ['sometimes', 'string', Password::min(6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.string' => 'Çalışan adı metin olmalıdır.',
            'username.unique' => 'Bu kullanıcı adı zaten alınmış.',
            'username.alpha_dash' => 'Kullanıcı adı yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }
}
