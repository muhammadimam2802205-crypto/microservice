<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Endpoint cek kesehatan service (closure, tanpa logika bisnis).
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'order-service',
]));

// Didaftarkan sebelum /orders/{id} agar path tidak ambigu.
Route::get('/orders/user/{userId}', [OrderController::class, 'byUser'])->whereNumber('userId');
Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
Route::post('/orders', [OrderController::class, 'store']);

// Endpoint order-service lain ditambahkan bertahap mulai T12.
