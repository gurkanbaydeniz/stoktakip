<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'barcode' => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'barcode')->where('company_id', $this->user()->company_id),
            ],
            'unit' => ['nullable', 'string', 'max:20'],
            'critical_stock_level' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ürün adı zorunludur.',
            'barcode.unique' => 'Bu barkod bu şirkette zaten kayıtlı.',
            'critical_stock_level.min' => 'Kritik stok seviyesi 0 veya daha büyük olmalıdır.',
        ];
    }
}
