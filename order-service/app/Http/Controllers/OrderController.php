<?php

namespace App\Http\Controllers;

use App\Exceptions\CartServiceUnavailableException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductServiceUnavailableException;
use App\Exceptions\ProductUnavailableException;
use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    // OrderService disuntikkan otomatis oleh Laravel.
    public function __construct(private OrderService $orderService)
    {
    }

    // POST /orders — checkout isi keranjang menjadi pesanan.
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->checkout($request->validated()['user_id']);

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => $order,
            ], 201);
        } catch (EmptyCartException | ProductUnavailableException | InsufficientStockException $e) {
            // Kondisi bisnis yang membuat checkout ditolak.
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (CartServiceUnavailableException | ProductServiceUnavailableException $e) {
            // Service lain tidak bisa dihubungi: bukan salah client.
            Log::error('Service lain tidak dapat dihubungi saat checkout', [
                'error' => $e->getMessage(),
            ]);

            $body = [
                'message' => $e->getMessage(),
            ];

            // Detail teknis hanya dikirim jika APP_DEBUG=true.
            if (config('app.debug')) {
                $body['error'] = $e->getMessage();
            }

            return response()->json($body, 503);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    // Menangani error tak terduga dan mencatat detailnya ke log.
    private function errorResponse(Throwable $e): JsonResponse
    {
        Log::error('Gagal membuat pesanan', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $body = [
            'message' => 'Gagal membuat pesanan',
        ];

        // Detail error hanya dikirim jika APP_DEBUG=true.
        if (config('app.debug')) {
            $body['error'] = $e->getMessage();
            $body['location'] = $e->getFile() . ':' . $e->getLine();
        }

        return response()->json($body, 500);
    }
}
