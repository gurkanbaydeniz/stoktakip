<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // İrsaliye fotoğrafı / dosya ekleri. StokAI (OCR) modülü bu tabloyu okuyup
        // işleyecek: ocr_status + ocr_payload alanları bu yüzden baştan mevcut.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // yükleyen
            $table->nullableMorphs('attachable'); // batch | stock_movement | product
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('kind', 30)->default('waybill'); // waybill | product_photo | other
            $table->string('ocr_status', 20)->default('pending'); // pending | processing | completed | failed
            $table->json('ocr_payload')->nullable();
            $table->timestamp('ocr_processed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
