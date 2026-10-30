<?php

namespace App\Exceptions;

use RuntimeException;

class CartServiceUnavailableException extends RuntimeException
{
    /**
     * Cart-service tidak bisa dihubungi (transport error, timeout, atau 5xx).
     */
    public function __construct()
    {
        parent::__construct('Cart service tidak dapat dihubungi, coba lagi nanti');
    }
}
