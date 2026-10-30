<?php

namespace App\Clients;

use App\Exceptions\CartServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CartServiceClient
{
    /**
     * Mengambil isi keranjang user dari cart-service.
     *
     * 200 + data -> array keranjang (items, total_quantity). Kegagalan lain
     * dicatat ke log lalu dilempar sebagai CartServiceUnavailableException.
     */
    public function getCart(int $userId): array
    {
        $baseUrl = (string) config('services.cart.url');
        $timeout = (int) min(5, max(1, (int) config('services.cart.timeout', 5)));
        $url = rtrim($baseUrl, '/')."/carts/{$userId}";

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout)
                ->get("/carts/{$userId}");
        } catch (Throwable $e) {
            Log::error('Gagal menghubungi cart-service', [
                'service' => 'cart-service',
                'url' => $url,
                'user_id' => $userId,
                'timeout' => $timeout,
                'error' => $e->getMessage(),
            ]);

            throw new CartServiceUnavailableException();
        }

        $data = $response->json()['data'] ?? null;

        if ($response->successful() && is_array($data)) {
            return $data;
        }

        Log::error('Respons cart-service tidak sesuai kontrak', [
            'service' => 'cart-service',
            'url' => $url,
            'user_id' => $userId,
            'status' => $response->status(),
        ]);

        throw new CartServiceUnavailableException();
    }

    /**
     * Mengosongkan keranjang user. Body respons tidak dipakai, hanya status 2xx
     * yang dianggap berhasil.
     */
    public function clearCart(int $userId): void
    {
        $baseUrl = (string) config('services.cart.url');
        $timeout = (int) min(5, max(1, (int) config('services.cart.timeout', 5)));
        $url = rtrim($baseUrl, '/')."/carts/{$userId}";

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout)
                ->delete("/carts/{$userId}");
        } catch (Throwable $e) {
            Log::error('Gagal mengosongkan keranjang', [
                'service' => 'cart-service',
                'url' => $url,
                'user_id' => $userId,
                'timeout' => $timeout,
                'error' => $e->getMessage(),
            ]);

            throw new CartServiceUnavailableException();
        }

        if (! $response->successful()) {
            Log::error('Cart-service menolak pengosongan keranjang', [
                'service' => 'cart-service',
                'url' => $url,
                'user_id' => $userId,
                'status' => $response->status(),
            ]);

            throw new CartServiceUnavailableException();
        }
    }
}
