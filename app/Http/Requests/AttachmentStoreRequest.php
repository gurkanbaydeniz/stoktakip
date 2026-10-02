<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentStoreRequest extends FormRequest
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
            // Mobil kamera fotoğrafları (jpg/png/heic/webp) ve taranmış irsaliye PDF'leri
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
            'kind' => ['nullable', 'string', Rule::in([Attachment::KIND_WAYBILL, Attachment::KIND_PRODUCT_PHOTO, 'other'])],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Dosya zorunludur.',
            'file.mimes' => 'Dosya yalnızca jpg, jpeg, png, webp, heic veya pdf olabilir.',
            'file.max' => 'Dosya boyutu en fazla 10 MB olabilir.',
            'kind.in' => 'Tür yalnızca waybill, product_photo veya other olabilir.',
            'batch_id.exists' => 'Parti bulunamadı.',
        ];
    }
}
