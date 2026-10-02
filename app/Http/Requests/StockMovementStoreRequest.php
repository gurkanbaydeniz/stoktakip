<?php

namespace App\Http\Requests;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // "adjustment" yalnızca admin; "out" tüm oturum açmış kullanıcılar.
        return $this->input('type') !== StockMovement::TYPE_ADJUSTMENT
            || $this->user()->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', 'string', Rule::in([StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUSTMENT])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'batch_id' => [
                'nullable', 'integer',
                Rule::exists('batches', 'id')->where('product_id', $this->input('product_id')),
            ],
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
            'type.in' => 'Tip yalnızca out veya adjustment olabilir.',
            'quantity.required' => 'Miktar zorunludur.',
            'quantity.not_in' => 'Miktar sıfır olamaz.',
            'batch_id.exists' => 'Parti bulunamadı veya bu ürüne ait değil.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Düzeltme (adjustment) hareketi için yönetici yetkisi gerekir.');
    }
}
