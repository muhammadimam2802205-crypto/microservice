<?php

namespace App\Exceptions;

use RuntimeException;

class ProductUnavailableException extends RuntimeException
{
    /**
     * Produk di keranjang sudah tidak ada di product-service (404).
     */
    public function __construct(int $productId)
    {
        parent::__construct("Produk dengan id {$productId} sudah tidak tersedia");
    }
}
