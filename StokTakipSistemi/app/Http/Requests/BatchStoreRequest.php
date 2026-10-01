<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchStoreRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'expiry_date' => ['nullable', 'date'],
            'batch_code' => ['nullable', 'string', 'max:100'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Ürün seçilmesi zorunludur.',
            'product_id.exists' => 'Ürün bulunamadı.',
            'quantity.required' => 'Miktar zorunludur.',
            'quantity.gt' => 'Miktar sıfırdan büyük olmalıdır.',
            'expiry_date.date' => 'SKT geçerli bir tarih olmalıdır.',
        ];
    }
}
