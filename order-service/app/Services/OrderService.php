<?php

namespace App\Services;

use App\Clients\CartServiceClient;
use App\Clients\ProductServiceClient;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartServiceClient $cartClient,
        private readonly ProductServiceClient $productClient,
    ) {}

    /**
     * Mengubah isi keranjang user menjadi pesanan.
     *
     * Validasi bisnis dilakukan lebih dulu, lalu order + item disimpan dalam
     * satu transaksi. Pengosongan keranjang bersifat best effort: kegagalan
     * hanya dicatat sebagai warning dan tidak membatalkan pesanan.
     *
     * @return array{id: int, user_id: int, status: string, total_amount: float, items: list<array{product_id: int, product_name: string, unit_price: float, quantity: int, subtotal: float}>, created_at: mixed, updated_at: mixed}
     */
    public function checkout(int $userId): array
    {
        $cart = $this->cartClient->getCart($userId);
        $items = $cart['items'] ?? [];

        if ($items === []) {
            throw new EmptyCartException();
        }

        $rows = [];
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            $product = $this->productClient->getProduct($productId);

            if ($product === null) {
                throw new ProductUnavailableException($productId);
            }

            $stock = (int) $product['stock'];

            if ($quantity > $stock) {
                throw new InsufficientStockException((string) $product['name'], $quantity, $stock);
            }

            // Harga dari product-service berupa teks, mis. "750000.00".
            $unitPrice = (float) $product['price'];
            $subtotal = round($unitPrice * $quantity, 2);
            $totalAmount += $subtotal;

            // Snapshot nama & harga saat checkout.
            $rows[] = [
                'product_id' => $productId,
                'product_name' => (string) $product['name'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        $totalAmount = round($totalAmount, 2);

        // Seluruh order + item tersimpan atau tidak sama sekali.
        $order = DB::transaction(function () use ($userId, $totalAmount, $rows): Order {
            $order = Order::create([
                'user_id' => $userId,
                'status' => 'PENDING',
                'total_amount' => $totalAmount,
            ]);

            $order->items()->createMany($rows);

            return $order;
        });

        $order->load('items');

        // Best effort: pesanan sudah tersimpan, kegagalan clear tidak boleh
        // membatalkan pesanan.
        try {
            $this->cartClient->clearCart($userId);
        } catch (Throwable $e) {
            Log::warning('Pesanan tersimpan tetapi keranjang gagal dikosongkan', [
                'order_id' => $order->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->toPayload($order);
    }

    /**
     * Mengambil satu pesanan lengkap dengan itemnya.
     *
     * Relasi items di-eager load agar tidak terjadi query N+1.
     *
     * @return array{id: int, user_id: int, status: string, total_amount: float, items: list<array{product_id: int, product_name: string, unit_price: float, quantity: int, subtotal: float}>, created_at: mixed, updated_at: mixed}
     */
    public function find(int $id): array
    {
        $order = Order::with('items')->find($id);

        if ($order === null) {
            throw new OrderNotFoundException($id);
        }

        return $this->toPayload($order);
    }

    /**
     * Mengambil daftar pesanan milik satu user, terbaru di atas.
     *
     * Relasi items di-eager load agar tidak terjadi query N+1. Filter status
     * diterapkan bila parameter diisi (T13).
     *
     * @return list<array{id: int, user_id: int, status: string, total_amount: float, items: list<array{product_id: int, product_name: string, unit_price: float, quantity: int, subtotal: float}>, created_at: mixed, updated_at: mixed}>
     */
    public function listForUser(int $userId, ?string $status = null): array
    {
        $query = Order::with('items')->where('user_id', $userId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order): array => $this->toPayload($order))
            ->all();
    }

    /**
     * Bentuk payload pesanan lengkap sesuai kontrak response.
     *
     * @return array{id: int, user_id: int, status: string, total_amount: float, items: list<array{product_id: int, product_name: string, unit_price: float, quantity: int, subtotal: float}>, created_at: mixed, updated_at: mixed}
     */
    private function toPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ])->all(),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
