<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => route('attachments.download', ['attachment' => $this->id], false),
            'batch_id' => $this->attachable_type === (new Batch)->getMorphClass()
                ? $this->attachable_id
                : null,
            'uploader' => $this->when($this->relationLoaded('uploader') && $this->uploader !== null, new UserResource($this->uploader)),
            'ocr_status' => $this->ocr_status, // StokAI: pending | processing | completed | failed
            'ocr_processed_at' => $this->ocr_processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
