<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * İrsaliye/fotoğraf yükleme. Dosyalar özel (private) diskte şirket bazlı
 * klasörlerde tutulur; erişim kimlik doğrulamalı indirme ucu üzerinden olur.
 * StokAI (OCR) modülü bu kayıtları ocr_status/ocr_payload ile işleyecek.
 */
class AttachmentController extends Controller
{
    /**
     * Yükleme (multipart/form-data). İsteğe bağlı olarak bir partiye bağlanır.
     */
    public function store(AttachmentStoreRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $company = $request->user()->company;

        $batch = null;
        if ($request->filled('batch_id')) {
            $batch = Batch::query()
                ->where('company_id', $company->id)
                ->findOrFail($request->integer('batch_id'));
        }

        $path = $file->store("attachments/{$company->id}/".now()->format('Y/m'), 'local');

        $attachment = Attachment::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'kind' => $request->input('kind', Attachment::KIND_WAYBILL),
        ]);

        if ($batch !== null) {
            $attachment->attachable()->associate($batch);
            $attachment->save();
        }

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Liste. Filtreler: ?batch_id=, ?kind=, ?ocr_status=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $attachments = Attachment::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('batch_id'), fn ($q) => $q->where(
                fn ($q) => $q->where('attachable_type', (new Batch)->getMorphClass())
                    ->where('attachable_id', $request->integer('batch_id'))
            ))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->when($request->filled('ocr_status'), fn ($q) => $q->where('ocr_status', $request->string('ocr_status')))
            ->with('uploader')
            ->latest('id')
            ->paginate(20);

        return AttachmentResource::collection($attachments);
    }

    public function show(Request $request, Attachment $attachment): AttachmentResource
    {
        abort_unless($attachment->company_id === $request->user()->company_id, 404);

        return new AttachmentResource($attachment->load('uploader'));
    }

    /**
     * Kimlik doğrulamalı indirme. ?inline=1 ile görüntüleme (WebView/mobil önizleme).
     */
    public function download(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->company_id === $request->user()->company_id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Dosya bulunamadı.');

        if ($request->boolean('inline')) {
            return response()->file(
                Storage::disk($attachment->disk)->path($attachment->path),
                ['Content-Type' => $attachment->mime_type]
            );
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /**
     * Silme (yalnızca admin): dosyayı da diskten kaldırır.
     */
    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        abort_unless($attachment->company_id === $request->user()->company_id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Ek silindi.']);
    }
}
