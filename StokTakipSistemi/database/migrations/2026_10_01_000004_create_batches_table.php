<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parti/lot: SKT takibinin temel birimi. Her stok girişi bir batch olur.
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code', 100)->nullable(); // lot / parti no
            $table->date('expiry_date')->nullable()->index(); // SKT
            $table->decimal('quantity', 12, 3)->unsigned(); // giriş miktarı
            $table->decimal('unit_cost', 12, 2)->nullable(); // alış fiyatı (opsiyonel)
            $table->string('supplier_name')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'expiry_date']);
            $table->index(['company_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
