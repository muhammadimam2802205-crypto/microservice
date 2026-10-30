<?php

use Illuminate\Support\Facades\Route;

// Endpoint cek kesehatan service (closure, tanpa logika bisnis).
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'order-service',
]));

// Endpoint order-service lain ditambahkan bertahap mulai T10.
