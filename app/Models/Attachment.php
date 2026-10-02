<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['company_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'kind', 'ocr_status', 'ocr_payload'])]
class Attachment extends Model
{
    public const KIND_WAYBILL = 'waybill';

    public const KIND_PRODUCT_PHOTO = 'product_photo';

    public const OCR_PENDING = 'pending';

    public const OCR_PROCESSING = 'processing';

    public const OCR_COMPLETED = 'completed';

    public const OCR_FAILED = 'failed';

    protected $attributes = [
        'disk' => 'local',
        'kind' => self::KIND_WAYBILL,
        'ocr_status' => self::OCR_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'ocr_payload' => 'array',
            'ocr_processed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
