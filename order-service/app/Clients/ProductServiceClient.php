<?php

namespace App\Clients;

use App\Exceptions\ProductServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductServiceClient
{
    /**
     * Mengambil detail produk dari product-service.
     *
     * 200 + data -> array produk; 404 -> null (produk memang tidak ada, bukan
     * kegagalan service). Kegagalan lain dicatat ke log lalu dilempar sebagai
     * ProductServiceUnavailableException.
     */
    public function getProduct(int $productId): ?array
    {
        $baseUrl = (string) config('services.product.url');
        $timeout = (int) min(5, max(1, (int) config('services.product.timeout', 5)));
        $url = rtrim($baseUrl, '/')."/products/{$productId}";

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout)
                ->get("/products/{$productId}");
        } catch (Throwable $e) {
            Log::error('Gagal menghubungi product-service', [
                'service' => 'product-service',
                'url' => $url,
                'product_id' => $productId,
                'timeout' => $timeout,
                'error' => $e->getMessage(),
            ]);

            throw new ProductServiceUnavailableException();
        }

        // Produk tidak ditemukan: bukan kegagalan service, service layer yang
        // memutuskan menjadi 409.
        if ($response->status() === 404) {
            return null;
        }

        $data = $response->json()['data'] ?? null;

        if ($response->successful() && is_array($data)) {
            return $data;
        }

        Log::error('Respons product-service tidak sesuai kontrak', [
            'service' => 'product-service',
            'url' => $url,
            'product_id' => $productId,
            'status' => $response->status(),
        ]);

        throw new ProductServiceUnavailableException();
    }
}
