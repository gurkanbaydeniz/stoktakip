<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('barcode', 100)->nullable();
            $table->string('unit', 20)->default('adet'); // adet, kg, lt, pk...
            $table->decimal('critical_stock_level', 12, 3)->unsigned()->default(0); // kritik stok sınırı
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
