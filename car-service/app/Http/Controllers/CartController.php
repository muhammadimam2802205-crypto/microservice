<?php

namespace App\Http\Controllers;

use App\Exceptions\CartItemNotFoundException;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class CartController extends Controller
{
    // CartService disuntikkan otomatis oleh Laravel.
    public function __construct(private CartService $cartService)
    {
    }

    // GET /carts/{userId}
    public function show(int $userId): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Berhasil mengambil keranjang',
                'data' => $this->cartService->getCart($userId),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'mengambil keranjang');
        }
    }

    // POST /carts
    public function store(AddCartItemRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $item = $this->cartService->addItem(
                $data['user_id'],
                $data['product_id'],
                $data['quantity'] ?? 1
            );

            // 201 jika item baru dibuat, 200 jika item sudah ada.
            return response()->json([
                'message' => 'Item berhasil ditambahkan ke keranjang',
                'data' => $item,
            ], $item->wasRecentlyCreated ? 201 : 200);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'menambahkan item');
        }
    }

    // PUT /carts/items/{id}
    public function update(
        UpdateCartItemRequest $request,
        int $id
    ): JsonResponse {
        try {
            $item = $this->cartService->updateQuantity(
                $id,
                $request->validated()['quantity']
            );

            return response()->json([
                'message' => 'Jumlah item berhasil diperbarui',
                'data' => $item,
            ]);
        } catch (CartItemNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'memperbarui item');
        }
    }

    // DELETE /carts/items/{id}
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cartService->removeItem($id);

            return response()->json([
                'message' => 'Item berhasil dihapus dari keranjang',
            ]);
        } catch (CartItemNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'menghapus item');
        }
    }

    // DELETE /carts/{userId}
    public function clear(int $userId): JsonResponse
    {
        try {
            $deleted = $this->cartService->clearCart($userId);

            return response()->json([
                'message' => 'Keranjang berhasil dikosongkan',
                'deleted_items' => $deleted,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse(
                $e,
                'mengosongkan keranjang'
            );
        }
    }

    // Menangani error tak terduga dan mencatat detailnya ke log.
    private function errorResponse(
        Throwable $e,
        string $action
    ): JsonResponse {
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