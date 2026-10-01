<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StaffStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota düzeyinde admin middleware'i ile korunur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', Password::min(6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Çalışan adı zorunludur.',
            'email.required' => 'E-posta adresi zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi giriniz.',
            'email.unique' => 'Bu e-posta adresi zaten kayıtlı.',
            'username.unique' => 'Bu kullanıcı adı zaten alınmış.',
            'password.required' => 'Şifre zorunludur.',
        ];
    }
}
