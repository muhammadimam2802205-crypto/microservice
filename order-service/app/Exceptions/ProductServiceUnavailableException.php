<?php

namespace App\Exceptions;

use RuntimeException;

class ProductServiceUnavailableException extends RuntimeException
{
    /**
     * Product-service tidak bisa dihubungi (transport error, timeout, atau 5xx).
     */
    public function __construct()
    {
        parent::__construct('Product service tidak dapat dihubungi, coba lagi nanti');
    }
}
