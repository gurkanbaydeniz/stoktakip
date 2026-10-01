<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Değişmez stok hareketi kayıtları (denetim izi).
        // quantity işaretli deltadır: giriş +, çıkış -, düzeltme her ikisi olabilir.
        // Mevcut stok = SUM(quantity); batch kalanı = SUM(quantity where batch_id).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // işlemi yapan
            $table->string('type', 20); // in | out | adjustment
            $table->decimal('quantity', 12, 3);
            $table->string('waybill_number', 100)->nullable(); // irsaliye no
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'created_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
