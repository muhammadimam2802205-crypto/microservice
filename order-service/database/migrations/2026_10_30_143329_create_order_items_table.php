<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buat tabel order_items untuk menyimpan snapshot item pesanan.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            // Item ikut terhapus bila pesanannya dihapus.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id'); // id produk di product-service
            $table->string('product_name'); // snapshot nama saat checkout
            $table->decimal('unit_price', 15, 2); // snapshot harga saat checkout
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 15, 2); // unit_price * quantity
        });
    }

    /**
     * Hapus tabel order_items.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
