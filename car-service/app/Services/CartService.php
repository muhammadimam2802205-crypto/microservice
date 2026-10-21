<?php

namespace App\Services;

use App\Exceptions\CartItemNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Collection;

class CartService
{
    public function __construct(private ProductService $productService)
    {
        // Inisialisasi service jika diperlukan
    }
    // Ambil data keranjang
    public function getCart(int $userId): array
    {
        $items = CartItem::forUser($userId)->get();

        return [
            'user_id' => $userId,
            'items' => $items,
            'total_quantity' => $items->sum('quantity'),
        ];
    }

    // Tambah item ke keranjang
    public function addItem(
        int $userId,
        int $productId,
        int $quantity
    ): CartItem {
        $item = CartItem::firstOrNew([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        $item->quantity =
            ($item->exists ? $item->quantity : 0) + $quantity;

        $item->save();

        return $item;
    }

    // Perbarui jumlah item
    public function updateQuantity(
        int $id,
        int $quantity
    ): CartItem {
        $item = CartItem::find($id);

        if (!$item) {
            throw new CartItemNotFoundException($id);
        }

        $item->update([
            'quantity' => $quantity,
        ]);

        return $item;
    }

    // Hapus satu item dari keranjang
    public function removeItem(int $id): void
    {
        if (CartItem::destroy($id) === 0) {
            throw new CartItemNotFoundException($id);
        }
    }

    // Kosongkan seluruh keranjang milik pengguna
    public function clearCart(int $userId): int
    {
        return CartItem::where('user_id', $userId)->delete();
    }
}
