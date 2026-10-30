<?php

namespace App\Http\Controllers;

use App\Exceptions\CartServiceUnavailableException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductServiceUnavailableException;
use App\Exceptions\ProductUnavailableException;
use App\Http\Requests\ListOrdersRequest;
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
            return $this->errorResponse($e, 'membuat pesanan');
        }
    }

    // GET /orders/{id} — detail satu pesanan beserta itemnya.
    public function show(int $id): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Berhasil mengambil pesanan',
                'data' => $this->orderService->find($id),
            ]);
        } catch (OrderNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'mengambil pesanan');
        }
    }

    // GET /orders/user/{userId} — daftar pesanan milik satu user.
    public function byUser(int $userId, ListOrdersRequest $request): JsonResponse
    {
        try {
            $status = $request->validated()['status'] ?? null;

            return response()->json([
                'message' => 'Berhasil mengambil daftar pesanan',
                'data' => $this->orderService->listForUser($userId, $status),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'mengambil daftar pesanan');
        }
    }

    // Menangani error tak terduga dan mencatat detailnya ke log.
    private function errorResponse(Throwable $e, string $action): JsonResponse
    {
        Log::error("Gagal {$action}", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $body = [
            'message' => "Gagal {$action}",
        ];

        // Detail error hanya dikirim jika APP_DEBUG=true.
        if (config('app.debug')) {
            $body['error'] = $e->getMessage();
            $body['location'] = $e->getFile() . ':' . $e->getLine();
        }

        return response()->json($body, 500);
    }
}
