<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
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
        $productId = $this->route('product')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'barcode' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('products', 'barcode')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($productId),
            ],
            'unit' => ['sometimes', 'string', 'max:20'],
            'critical_stock_level' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'barcode.unique' => 'Bu barkod bu şirkette zaten kayıtlı.',
            'critical_stock_level.min' => 'Kritik stok seviyesi 0 veya daha büyük olmalıdır.',
        ];
    }
}
