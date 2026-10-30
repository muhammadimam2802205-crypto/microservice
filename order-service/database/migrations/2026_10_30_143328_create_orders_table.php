<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buat tabel orders untuk menyimpan pesanan hasil checkout.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index(); // pemilik pesanan (id dari cart-service)
            $table->string('status')->default('PENDING'); // PENDING | PAID
            $table->decimal('total_amount', 15, 2); // jumlah seluruh subtotal item
            $table->timestamps();
        });
    }

    /**
     * Hapus tabel orders.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
