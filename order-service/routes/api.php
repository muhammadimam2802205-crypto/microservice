<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Endpoint cek kesehatan service (closure, tanpa logika bisnis).
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'order-service',
]));

Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
Route::post('/orders', [OrderController::class, 'store']);

// Endpoint order-service lain ditambahkan bertahap mulai T12.
